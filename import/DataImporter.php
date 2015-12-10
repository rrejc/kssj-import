<?php
	class DataImporter {
		public $db;
		
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
			
			$doc = new DOMDocument();
			$doc->load($path);							
			
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
			
			$sql = 'INSERT INTO kssj_strukture (id_gesla, id_pomena, zap_st, struktura) VALUES ($1, $2, $3, $4) RETURNING id_strukture';
			$result = pg_query_params($this->db, $sql, array($entryId, $meaningId, $order, $struktura));
			$structureId = pg_fetch_result($result, 'id_strukture');			
			
			$kolokacijeElements = $skladStrukturaElement->getElementsByTagName('kolokacije');
			if ($kolokacijeElements->length == 0) {
				die ('Element kolokacije not found!');
			}
			$kolokacijeElement = $kolokacijeElements->item(0);
			$this->importKolokacije($kolokacijeElement, $entryId, $meaningId, $structureId);
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
			
			$sql = 'INSERT INTO kssj_kolokacije (id_gesla, id_pomena, id_strukture, zap_st, kolokacija) VALUES ($1, $2, $3, $4, $5) RETURNING id_kolokacije';
			$result = pg_query_params($this->db, $sql, array($entryId, $meaningId, $structureId, $order, $kolokacija));
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
			$zgled = $this->getInnerHtml($zgledElement);
			
			$sql = 'INSERT INTO kssj_zgledi (id_gesla, id_pomena, id_strukture, id_kolokacije, zap_st, zgled) VALUES ($1, $2, $3, $4, $5, $6) RETURNING id_zgleda';
			$result = pg_query_params($this->db, $sql, array($entryId, $meaningId, $structureId, $collocationId, $order, $zgled));
			$exampleId = pg_fetch_result($result, 'id_zgleda');
			return $exampleId;
		}
		
		private function getInnerHtml($element) {
			$innerHtml = '';
			
			$childNodes = $element->childNodes;
			foreach ($childNodes as $childNode) {
				$innerHtml .= $element->ownerDocument->saveHtml($childNode);
			}
			return $innerHtml;
		}
		
		private function getKolokacija($element) {
			$innerHtml = '';
			
			$childNodes = $element->childNodes;
			foreach ($childNodes as $childNode) {
				if ($childNode->nodeType == XML_ELEMENT_NODE && $childNode->nodeName == 'zgledi') {
					continue;
				}				
				$innerHtml .= $element->ownerDocument->saveHtml($childNode);
			}
			return $innerHtml;			
		}
	}
?>