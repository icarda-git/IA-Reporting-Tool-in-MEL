<?php
/**
 * upload.php
 *
 * Copyright 2013, Moxiecode Systems AB
 * Released under GPL License.
 *
 * License: http://www.plupload.com/license
 * Contributing: http://www.plupload.com/contributing
 */

// Make sure file is not cached (as it happens for example on iOS devices)
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

/* 
// Support CORS
header("Access-Control-Allow-Origin: *");
// other CORS headers if any...
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
	exit; // finish preflight CORS requests here
}
*/

// 5 minutes execution time
@set_time_limit(5 * 60);

// Uncomment this one to fake upload time
// usleep(5000);

// Settings
//$targetDir = ini_get("upload_tmp_dir") . DIRECTORY_SEPARATOR . "plupload";
$targetDir = 'uploads/ia_reports';
$cleanupTargetDir = true; // Remove old files
$maxFileAge = 5 * 3600; // Temp file age in seconds


// Create target dir
if (!file_exists($targetDir)) {
    @mkdir($targetDir);
}

// Get a file name
if (isset($_REQUEST["name"]))
    $fileName = $_REQUEST["name"];
elseif (!empty($_FILES))
    $fileName = $_FILES["file"]["name"];
else
    $fileName = uniqid("file_");

$filePath = $targetDir . DIRECTORY_SEPARATOR . $fileName;

$file_parts = explode('.', $fileName);
$ext = strtolower(array_pop($file_parts));
$realName = implode('.', $file_parts);

$_allowedExtension = array(
    'jpg', 'jpeg', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'tif', 'ppt', 'pptx', 'png', 'gif', 'zip', 'rar'
);

if (!in_array($ext, $_allowedExtension))
    die(json_encode(array(
        'error' => array(
            'code' => 100,
            'message' => 'Allowed extensions: <i>' . implode(', ', $_allowedExtension) . '</i> <br>Please try again."'
        )
    ), true));

$realName = $file_parts[0];
$realName = preg_replace('/\W/', '_', $realName);
$realName = preg_replace('/_{2,}/', '_', $realName);
$realName = substr($realName, 0, 200);
$newFileName = md5(time() . rand(0, 9999)) . '-' . $realName . '.' . $ext;
$newFilePath = $targetDir . DIRECTORY_SEPARATOR . $newFileName;


// Chunking might be enabled
$chunk = isset($_REQUEST["chunk"]) ? intval($_REQUEST["chunk"]) : 0;
$chunks = isset($_REQUEST["chunks"]) ? intval($_REQUEST["chunks"]) : 0;


// Remove old temp files	
if ($cleanupTargetDir) {
    if (!is_dir($targetDir) || !$dir = opendir($targetDir))
        die(json_encode(array(
            'error' => array(
                'code' => 100,
                'message' => 'Failed to open temp directory."'
            )
        ), true));

    while (($file = readdir($dir)) !== false) {
        $tmpfilePath = $targetDir . DIRECTORY_SEPARATOR . $file;

        // If temp file is current file proceed to the next
        if ($tmpfilePath == "{$filePath}.part") {
            continue;
        }

        // Remove temp file if it is older than the max age and is not the current file
        if (preg_match('/\.part$/', $file) && (filemtime($tmpfilePath) < time() - $maxFileAge)) {
            @unlink($tmpfilePath);
        }
    }
    closedir($dir);
}


// Open temp file
if (!$out = @fopen("{$filePath}.part", $chunks ? "ab" : "wb"))
    die(json_encode(array(
        'error' => array(
            'code' => 102,
            'message' => 'Failed to open output stream."'
        )
    ), true));

if (!empty($_FILES)) {
    if ($_FILES["file"]["error"] || !is_uploaded_file($_FILES["file"]["tmp_name"]))
        die(json_encode(array(
            'error' => array(
                'code' => 103,
                'message' => 'Failed to move uploaded file."'
            )
        ), true));

    // Read binary input stream and append it to temp file
    if (!$in = @fopen($_FILES["file"]["tmp_name"], "rb"))
        die(json_encode(array(
            'error' => array(
                'code' => 101,
                'message' => 'Failed to open input stream."'
            )
        ), true));
} else {
    if (!$in = @fopen("php://input", "rb"))
        die(json_encode(array(
            'error' => array(
                'code' => 101,
                'message' => 'Failed to open input stream."'
            )
        ), true));
}

while ($buff = fread($in, 4096))
    fwrite($out, $buff);

@fclose($out);
@fclose($in);

// Check if file has been uploaded
if (!$chunks || $chunk == $chunks - 1)
    rename("{$filePath}.part", $newFilePath); // Strip the temp .part suffix off

die(json_encode(array(
    'result' => $newFileName
), true));
