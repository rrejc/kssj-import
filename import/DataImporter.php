<?php
	class DataImporter {
		public $db;
		
		public function cleanup() {
			pg_query($this->db, 'TRUNCATE TABLE kssj_gesla CASCADE');
			pg_query($this->db, 'TRUNCATE TABLE kssj_vrste_strukture CASCADE');
			pg_query($this->db, 'TRUNCATE TABLE sssj_bes_vrste CASCADE');
		}
		
		public function importPartOfSpeech() {
			$pos = array (
				1 => 'samostalnik',
				2 => 'pridevnik',
				3 => 'glagol',
				4 => 'prislov'
			);
			
			foreach ($pos as $id_bes_vrste => $bes_vrsta) {			
				$sql = 'INSERT INTO sssj_bes_vrste (id_bes_vrste, bes_vrsta) VALUES ($1, $2)';							
				$result = pg_query_params($this->db, $sql, array($id_bes_vrste, $bes_vrsta));
			}			
		}
		
		public function importFile($path) {
			$reader = new XMLReader();
			if (!$reader->open($path)) {
				die ("Unable to open file $path");
			}
			
			pg_query("BEGIN");
			
			$xml = '<?xml version="1.0" encoding="utf-8"?>' . file_get_contents($path);
			$doc = new DOMDocument('1.0', 'utf-8');
			$doc->loadXML($xml);			
			
			$documentElement = $doc->documentElement;
			if ($documentElement->nodeName != 'clanek') {
				die ("Document element should be 'clanek'!");
			}		
			
			$this->importClanek($documentElement);
			
			pg_query("COMMIT");
		}
		
		// <clanek>
		private function importClanek($documentElement) {
			$glavaElements = $documentElement->getElementsByTagName('glava');
			if ($glavaElements->length == 0) {
				die ('Element glava not found!');
			}			
			$entryId = $this->importGlava($glavaElements->item(0));
			
			$gesloElements = $documentElement->getElementsByTagName('geslo');
			if ($gesloElements->length == 0) {
				die ('Element geslo not found!');
			}
			$this->importGeslo($gesloElements->item(0), $entryId);
		}
		
		// <glava>
		private function importGlava($glavaElement) {
			$oblikaElements = $glavaElement->getElementsByTagName('oblika');
			if ($oblikaElements->length == 0) {
				die ('Element oblika not found!');
			}
			$oblikaElement = $oblikaElements->item(0);
			
			$iztocnicaElements = $oblikaElement->getElementsByTagName('iztocnica');
			if ($iztocnicaElements->length == 0) {
				die ('Element iztocnica not found!');
			}
			$iztocnicaElement = $iztocnicaElements->item(0);
			$iztocnica = $iztocnicaElement->nodeValue;			
			
			$zaglavjeElements = $glavaElement->getElementsByTagName('zaglavje');
			if ($zaglavjeElements->length == 0) {
				die ('Element zaglavje not found!');
			}
			$zaglavjeElement = $zaglavjeElements->item(0);
			
			$besVrstaElements = $zaglavjeElement->getElementsByTagName('besedna_vrsta');
			if ($besVrstaElements->length == 0) {
				die ('Element besedna_vrsta not found!');
			}
			$besVrstaElement = $besVrstaElements->item(0);
			
			switch ($besVrstaElement->nodeValue) {
				case 'samostalnik':
					$besVrsta = 1;
					break;
				case 'pridevnik':
					$besVrsta = 2;
					break;
				case 'glagol':
					$besVrsta = 3;
					break;
				case 'prislov':
					$besVrsta = 4;
					break;
				default:
					die ('Unknown part of speech!');
			}			

			$sql = 'INSERT INTO kssj_gesla (iztocnica, id_bes_vrste) VALUES ($1, $2) RETURNING id_gesla';							
			$result = pg_query_params($this->db, $sql, array($iztocnica, $besVrsta));
			return pg_fetch_result($result, 'id_gesla');
			return $entryId;														
		}
		
		// <geslo>
		private function importGeslo($gesloElement, $entryId) {
			$pomenElements = $gesloElement->getElementsbyTagName('pomen');
			if ($pomenElements->length == 0) {
				die ('Element pomen not found!');
			}
			
			$order = 0;
			foreach ($pomenElements as $pomenElement) {
				$order++;
				$this->importPomen($pomenElement, $entryId, $order);				
			}
		}
		
		// <pomen>
		private function importPomen($pomenElement, $entryId, $order) {
			$indikatorElements = $pomenElement->getElementsByTagName('indikator');
			if ($indikatorElements->length == 0) {
				die ('Element indikator not found');
			}
			$indikatorElement = $indikatorElements->item(0);
			$indikator = $indikatorElement->nodeValue;

			$sql = 'INSERT INTO kssj_pomeni (id_gesla, zap_st, indikator) VALUES ($1, $2, $3) RETURNING id_pomena';
			$result =  pg_query_params($this->db, $sql, array($entryId, $order, $indikator));
			$meaningId = pg_fetch_result($result, 'id_pomena');			
			
			$skladSkupineElements = $pomenElement->getElementsByTagName('skladenjske_skupine');
			if ($skladSkupineElements->length == 0) {
				die ('Element skladenjske_skupine not found!');
			}	
			$skladSkupineElement = $skladSkupineElements->item(0);
			$this->importSkladSkupine($skladSkupineElement, $entryId, $meaningId);							
		}
		
		
		// <skladenjske_skupine>
		private function importSkladSkupine($skladSkupineElement, $entryId, $meaningId) {
			$skladStrukturaElements = $skladSkupineElement->getElementsByTagName('skladenjska_struktura');
	
			$order = 0;
			foreach ($skladStrukturaElements as $skladStrukturaElement) {
				$order++;
				$this->importSkladStruktura($skladStrukturaElement, $entryId, $meaningId, $order);
			}
		}
		
		// <skladenjska_struktura>
		private function importSkladStruktura($skladStrukturaElement, $entryId, $meaningId, $order) {
			$strukturaElements = $skladStrukturaElement->getElementsByTagName('struktura');
			if ($strukturaElements->length == 0) {
				die ('Element struktura not found');
			}		
			$strukturaElement = $strukturaElements->item(0);
			$struktura = $strukturaElement->nodeValue;
			$vrstaStrukture = $this->importVrstaStrukture($struktura);
			
			$sql = 'INSERT INTO kssj_strukture (id_gesla, id_pomena, id_vrste_strukture, zap_st) VALUES ($1, $2, $3, $4) RETURNING id_strukture';
			$result = pg_query_params($this->db, $sql, array($entryId, $meaningId, $vrstaStrukture, $order));
			$structureId = pg_fetch_result($result, 'id_strukture');			
			
			$kolokacijeElements = $skladStrukturaElement->getElementsByTagName('kolokacije');
			if ($kolokacijeElements->length == 0) {
				die ('Element kolokacije not found!');
			}
			$kolokacijeElement = $kolokacijeElements->item(0);
			$this->importKolokacije($kolokacijeElement, $entryId, $meaningId, $structureId);
		}
		
		private function importVrstaStrukture($structure) {
			$sql = 'SELECT id_vrste_strukture FROM kssj_vrste_strukture WHERE struktura = $1';
			$result = pg_query_params($this->db, $sql, array($structure));
			$rows = pg_num_rows($result);
			if ($rows !== 0) {
				return pg_fetch_result($result, 'id_vrste_strukture');	
			}
			
			$plain = $structure;
			$plain = str_replace(' ', ' + ', $plain);
			$plain = str_replace('sbz0', 'samostalnik (0)', $plain);
			$plain = str_replace('sbz1', 'samostalnik (1)', $plain);
			$plain = str_replace('sbz2', 'samostalnik (2)', $plain);
			$plain = str_replace('sbz3', 'samostalnik (3)', $plain);
			$plain = str_replace('sbz4', 'samostalnik (4)', $plain);
			$plain = str_replace('sbz5', 'samostalnik (5)', $plain);
			$plain = str_replace('sbz6', 'samostalnik (6)', $plain);
			$plain = str_replace('pbz0', 'pridevnik (0)', $plain);
			$plain = str_replace('pbz1', 'pridevnik (1)', $plain);
			$plain = str_replace('pbz2', 'pridevnik (2)', $plain);
			$plain = str_replace('pbz3', 'pridevnik (3)', $plain);
			$plain = str_replace('pbz4', 'pridevnik (4)', $plain);
			$plain = str_replace('pbz5', 'pridevnik (5', $plain);
			$plain = str_replace('pbz6', 'pridevnik (6)', $plain);
			$plain = str_replace('gbz', 'glagol', $plain);
			$plain = str_replace('rbz', 'prislov', $plain);
			$plain = str_replace('SBZ0', 'samostalnik (0)', $plain);
			$plain = str_replace('SBZ1', 'samostalnik (1)', $plain);
			$plain = str_replace('SBZ2', 'samostalnik (2)', $plain);
			$plain = str_replace('SBZ3', 'samostalnik (3)', $plain);
			$plain = str_replace('SBZ4', 'samostalnik (4)', $plain);
			$plain = str_replace('SBZ5', 'samostalnik (5)', $plain);
			$plain = str_replace('SBZ6', 'samostalnik (6)', $plain);
			$plain = str_replace('PBZ0', 'pridevnik (0)', $plain);
			$plain = str_replace('PBZ1', 'pridevnik (1)', $plain);
			$plain = str_replace('PBZ2', 'pridevnik (2)', $plain);
			$plain = str_replace('PBZ3', 'pridevnik (3)', $plain);
			$plain = str_replace('PBZ4', 'pridevnik (4)', $plain);
			$plain = str_replace('PBZ5', 'pridevnik (5)', $plain);
			$plain = str_replace('PBZ6', 'pridevnik (6)', $plain);
			$plain = str_replace('GBZ', 'glagol', $plain);
			$plain = str_replace('RBZ', 'prislov', $plain);					
			
			$html = $structure;
			$html = str_replace(' ', ' + ', $html);
			$html = str_replace('sbz0', '<em>samostalnik<sub>0</sub></em>', $html);
			$html = str_replace('sbz1', '<em>samostalnik<sub>1</sub></em>', $html);
			$html = str_replace('sbz2', '<em>samostalnik<sub>2</sub></em>', $html);
			$html = str_replace('sbz3', '<em>samostalnik<sub>3</sub></em>', $html);
			$html = str_replace('sbz4', '<em>samostalnik<sub>4</sub></em>', $html);
			$html = str_replace('sbz5', '<em>samostalnik<sub>5</sub></em>', $html);
			$html = str_replace('sbz6', '<em>samostalnik<sub>6</sub></em>', $html);
			$html = str_replace('pbz0', '<em>pridevnik<sub>0</sub></em>', $html);
			$html = str_replace('pbz1', '<em>pridevnik<sub>1</sub></em>', $html);
			$html = str_replace('pbz2', '<em>pridevnik<sub>2</sub></em>', $html);
			$html = str_replace('pbz3', '<em>pridevnik<sub>3</sub></em>', $html);
			$html = str_replace('pbz4', '<em>pridevnik<sub>4</sub></em>', $html);
			$html = str_replace('pbz5', '<em>pridevnik<sub>5</sub></em>', $html);
			$html = str_replace('pbz6', '<em>pridevnik<sub>6</sub></em>', $html);
			$html = str_replace('gbz', '<em>glagol</em>', $html);
			$html = str_replace('rbz', '<em>prislov</em>', $html);
			$html = str_replace('SBZ0', '<strong>samostalnik<sub>0</sub></strong>', $html);
			$html = str_replace('SBZ1', '<strong>samostalnik<sub>1</sub></strong>', $html);
			$html = str_replace('SBZ2', '<strong>samostalnik<sub>2</sub></strong>', $html);
			$html = str_replace('SBZ3', '<strong>samostalnik<sub>3</sub></strong>', $html);
			$html = str_replace('SBZ4', '<strong>samostalnik<sub>4</sub></strong>', $html);
			$html = str_replace('SBZ5', '<strong>samostalnik<sub>5</sub></strong>', $html);
			$html = str_replace('SBZ6', '<strong>samostalnik<sub>6</sub></strong>', $html);
			$html = str_replace('PBZ0', '<strong>pridevnik<sub>0</sub></strong>', $html);
			$html = str_replace('PBZ1', '<strong>pridevnik<sub>1</sub></strong>', $html);
			$html = str_replace('PBZ2', '<strong>pridevnik<sub>2</sub></strong>', $html);
			$html = str_replace('PBZ3', '<strong>pridevnik<sub>3</sub></strong>', $html);
			$html = str_replace('PBZ4', '<strong>pridevnik<sub>4</sub></strong>', $html);
			$html = str_replace('PBZ5', '<strong>pridevnik<sub>5</sub></strong>', $html);
			$html = str_replace('PBZ6', '<strong>pridevnik<sub>6</sub></strong>', $html);
			$html = str_replace('GBZ', '<strong>glagol</strong>', $html);
			$html = str_replace('RBZ', '<strong>prislov</strong>', $html);			
			
			$sql = 'INSERT INTO kssj_vrste_strukture (struktura, opis_text, opis_html) VALUES ($1, $2, $3) RETURNING id_vrste_strukture';
			$result = pg_query_params($this->db, $sql, array($structure, $plain, $html));
			return pg_fetch_result($result, 'id_vrste_strukture');						
		}
		
		// <kolokacije>
		private function importKolokacije($kolokacijeElement, $entryId, $meaningId, $structureId) {
			$kolokacijaElements = $kolokacijeElement->getElementsByTagName('kolokacija');

			$order = 0;
			foreach ($kolokacijaElements as $kolokacijaElement) {
				$order++;
				$this->importKolokacija($kolokacijaElement, $entryId, $meaningId, $structureId, $order);
			}
		}
		
		// <kolokacija>
		private function importKolokacija($kolokacijaElement, $entryId, $meaningId, $structureId, $order)  {
			$kolokacija = $this->getKolokacija($kolokacijaElement);
			$kolokacijaText = $this->getKolokacijaText($kolokacijaElement);
			
			$sql = 'INSERT INTO kssj_kolokacije (id_gesla, id_pomena, id_strukture, zap_st, kolokacija, kolokacija_text) VALUES ($1, $2, $3, $4, $5, $6) RETURNING id_kolokacije';
			$result = pg_query_params($this->db, $sql, array($entryId, $meaningId, $structureId, $order, $kolokacija, $kolokacijaText));
			$collocationId = pg_fetch_result($result, 'id_kolokacije');

			$zglediElements = $kolokacijaElement->getElementsByTagName('zgledi');
			if ($zglediElements->length == 0) {
				die ('Element zgledi not found!');
			}
			$zglediElement = $zglediElements->item(0);
			$this->importZgledi($zglediElement, $entryId, $meaningId, $structureId, $collocationId);
		}
		
		// <zgledi>
		private function importZgledi($zglediElement, $entryId, $meaningId, $structureId, $collocationId) {
			$zgledElements = $zglediElement->getElementsByTagName('zgled');
			
			$order = 0;
			foreach ($zgledElements as $zgledElement) {
				$order++;
				$this->importZgled($zgledElement, $entryId, $meaningId, $structureId, $collocationId, $order);
			}
		}
		
		// <zgled>
		private function importZgled($zgledElement, $entryId, $meaningId, $structureId, $collocationId, $order) {
			$zgled= $this->parseZgled($zgledElement);
			
			$sql = 'INSERT INTO kssj_zgledi (id_gesla, id_pomena, id_strukture, id_kolokacije, zap_st, zgled, zgled_text, zgled_html) VALUES ($1, $2, $3, $4, $5, $6, $7, $8) RETURNING id_zgleda';
			$result = pg_query_params($this->db, $sql, array($entryId, $meaningId, $structureId, $collocationId, $order, $zgled->xml, $zgled->text, $zgled->html));
			$exampleId = pg_fetch_result($result, 'id_zgleda');
			return $exampleId;
		}
		
		private function parseZgled($element) {
			$obj = new stdClass();
			$obj->xml = '';
			$obj->text = '';
			$obj->html = '';
			
			$childNodes = $element->childNodes;
			foreach ($childNodes as $childNode) {
				if ($childNode->nodeType == XML_ELEMENT_NODE) {
					$obj->xml .= '<' . $childNode->nodeName . '>' . $childNode->nodeValue . '</' . $childNode->nodeName . '>'; 			
					$obj->text .= $childNode->nodeValue;		
					if ($childNode->nodeName == 'i') {
						$obj->html .= '<strong>' . $childNode->nodeValue . '</strong>';
					} else if ($childNode->nodeName == 'k') {
						$obj->html .= '<em>' . $childNode->nodeValue . '</em>';
					}
				} else if ($childNode->nodeType == XML_TEXT_NODE) {
					$obj->xml .=  $childNode->nodeValue;
					$obj->text .= $childNode->nodeValue;
					$obj->html .= $childNode->nodeValue;
				} 
			}
			return $obj;
		}
		
		private function getKolokacijaText($element) {
			$text = '';
			
			$childNodes = $element->childNodes;
			foreach ($childNodes as $childNode) {
				if ($childNode->nodeType == XML_ELEMENT_NODE) {
					if ($childNode->nodeName == 'zgledi') {
						continue;
					} else {
						if ($childNode->nodeName == 'ks') {
							foreach ($childNode->childNodes as $kNode) {
								if ($kNode->nodeName == 'k') {
									$text = $text . $kNode->nodeValue . ' ';
									break;
								}
							}
						}
						else
						{
							$text = $text . $childNode->nodeValue . ' ';
						}
					}
				}
			}
			
			return trim($text);			
		}
		
		private function getKolokacija($element) {
			$obj = new stdClass();
			$obj->data = array();
			
			$childNodes = $element->childNodes;
			foreach ($childNodes as $childNode) {
				if ($childNode->nodeType == XML_ELEMENT_NODE) {
					if ($childNode->nodeName == 'zgledi') {
						continue;
					} else {
						$item = new stdClass();
						if ($childNode->nodeName == 'ks') {
							$item->name = 'k';
							foreach ($childNode->childNodes as $kNode) {
								if ($kNode->nodeName == 'k') {
									$item->value = $kNode->nodeValue;
									break;
								}
							}
						}
						else
						{
							$item->name = $childNode->nodeName;
							$item->value = $childNode->nodeValue;
						}
						array_push($obj->data, $item);
					}
				}
			}
			return json_encode($obj, JSON_UNESCAPED_UNICODE);
		}
	}
?>