<?php
	require_once('../config.php');
	require_once('DataImporter.php');

	$db = pg_connect($connection_string);	
	$importer = new DataImporter();	
	$importer->db = $db;
	
	// Import part of speeches
	$importer->importPartOfSpeech();
	
	// Import data files (parameter 0 is script name)
	if (count($argv) == 1) {
		die ('Please pass data directory as (only) parameter!');
	}	
	$dirPath = $argv[1]; 
		
	$directory = new DirectoryIterator($dirPath);
	foreach ($directory as $file) {
		if ($file->isFile() && $file->getExtension() == 'xml') {
			$path = $file->getPathname();
			echo "Importing file $path...\n";			
			$importer->importFile($path);
		}
	}	
	
	pg_close($db);
?>