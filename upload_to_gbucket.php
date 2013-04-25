<?php
/*
	Upload an object to Google Storage bucket.
	james@ttard.info - jamesattard.com
*/

    // Access Key
    $key = 'xxx';

    // Secret
    $secret = 'yyy';

    $bucket = $argv[1];
    $remotefile = $argv[2];
    $localfile = $argv[3];

    include('GoogleStorage.class.php');

    // create
    $gs = new GoogleStorage($key, $secret);

    // put file into system -> bucket name, object name (file name visible in google storage, path/to/file - file path to local file)
    $r = $gs->putObjectFile($bucket, $remotefile, $localfile);

?>
