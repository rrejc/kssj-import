<?php
	require_once('../config.php');
	require_once('DataImporter.php');

	$db = pg_connect($connection_string) or die('Unable to connect to database server - check config.php!');	
	$importer = new DataImporter();	
	$importer->db = $db;
	
	date_default_timezone_set('Europe/Ljubljana'); // Should be set in php config
	
	// Cleanup
	$importer->cleanup();
	
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
			echo date('d.m.Y H:i:s') . " Importing file $path...\n";			
			$importer->importFile($path);
		}
	}	
	
	pg_close($db);
?>