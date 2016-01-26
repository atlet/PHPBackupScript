<?php

/*
 * Author: Andraž Prinčič @ 2016 http://www.princic.net
 * License: GNU GENERAL PUBLIC LICENSE Version 2
 */

require 'vendor/autoload.php';
require 'config.php';

use Aws\S3\S3Client;
use Aws\Common\Credentials\Credentials;

$credentials = new Credentials(ACCESS_KEY, SECRET_KEY);

$errors = array();

// Instantiate the S3 client with your AWS credentials
$client = S3Client::factory(array(
            'credentials' => $credentials
        ));

$client->createBucket(array('Bucket' => BUCKET));
$client->waitUntil('BucketExists', array('Bucket' => BUCKET));

$tmpDate = date("Y-m-d-H-i-s");
$folder = $tmpDate . "/";

foreach ($sqlTables as $value) {
    $SQLFileName = "{$value}-{$tmpDate}.sql.gz";

    $backupSqlFile = getcwd() . "/" . $SQLFileName;
    $commandSQL = "mysqldump --add-drop-table --routines -u {$mySqlUsername} -p{$mySqlPassword}  {$value} | gzip > " . $backupSqlFile;
    $ret = system($commandSQL);
    
    if (!empty($ret)) {
        $errors[] = $ret;
    }

    $result = $client->putObject(array(
        'Bucket' => BUCKET,
        'Key' => $folder . $SQLFileName,
        'SourceFile' => $backupSqlFile
    ));

    $client->waitUntil('ObjectExists', array(
        'Bucket' => BUCKET,
        'Key' => $folder . $SQLFileName
    ));

    unlink($backupSqlFile);
}

foreach ($directories as $key => $value) {
    $directoryFileName = "{$key}-{$tmpDate}.tar.gz";
    $backupDirectoryFileName = getcwd() . "/" . $directoryFileName;

    $command = "tar -czf $backupDirectoryFileName -C {$value} {$key}";
    $ret = system($command);
    
    if (!empty($ret)) {
        $errors[] = $ret;
    }

    $result = $client->putObject(array(
        'Bucket' => BUCKET,
        'Key' => $folder . $directoryFileName,
        'SourceFile' => $backupDirectoryFileName
    ));

    $client->waitUntil('ObjectExists',
            array(
        'Bucket' => BUCKET,
        'Key' => $folder . $directoryFileName
    ));

    unlink($backupDirectoryFileName);
}

if (SEND_MAIL) {
    $transport = Swift_MailTransport::newInstance();
    $mailer = Swift_Mailer::newInstance($transport);

    $newMail = Swift_Message::newInstance();
    $newMail->setSubject("[" . BUCKET . "] Backup - " . date("d.m.Y H:i:s"));
    $newMail->setFrom($from);
    $newMail->setTo($to);
    $newMail->setBody("Backup successfully created!\n\n" . print_r($errors, TRUE));

    $result = $mailer->send($newMail);
}
