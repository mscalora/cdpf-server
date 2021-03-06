<?php
	$dataDir = isset($_ENV['OPENSHIFT_DATA_DIR']) ? $_ENV['OPENSHIFT_DATA_DIR'] : (isset($_SERVER['OPENSHIFT_DATA_DIR']) ? $_SERVER['OPENSHIFT_DATA_DIR'] : '/var/www/data/');
	$fromPi = isset($_REQUEST['list']) && count($_REQUEST)==1;
	$title = "Connected Digital Photo Frame";

	require_once 'twig/lib/Twig/Autoloader.php';
	Twig_Autoloader::register();
	$twig = new Twig_Environment(new Twig_Loader_Filesystem('.'));
	
	if (!$fromPi) {
		require("auth.php");
	}
	
	// test for image file names
	function isImageName($name) {
		return preg_match('/^[^.\/][^\/]*\.(jpe?g|png|gif)$/i',$name)>0;
	}

	// for debugging
	if (isset($_REQUEST['phpinfo'])) {
		phpinfo();
		exit;
	}
	
	// handle serving image files 
	if (isset($_REQUEST['f'])) {
		if (isImageName($_REQUEST['f']) && is_file($dataDir . $_REQUEST['f'])) {
	
			// set content type if a legal image extention 
			$file = $dataDir . $_REQUEST['f'];
			if (preg_match('/\.gif/i',$file)) {
				header('Content-Type: image/gif');
			} else if (preg_match('/\.jpe?g/i',$file)) {
				header('Content-Type: image/jpeg');
			} else if (preg_match('/\.png/i',$file)) {
				header('Content-Type: image/png');
			} else {
				header ("HTTP/1.0 404 Not Found");
				exit;
			}

			// Checking if the client is validating his cache and if it is current.
			if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && (strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) == filemtime($file))) {
				// Client's cache IS current, so we just respond '304 Not Modified'.
				header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($file)).' GMT', true, 304);
				exit;
			}
			
			// insert Last-Modified header for wget's -N option
			header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($file)).' GMT', true, 200);
			header('Content-Length: '.filesize($file));
			// for HEAD requests (which WGET claims to use) we don't send the content
			if ($_SERVER['REQUEST_METHOD']=='HEAD') {
				exit;
			}
			
			// tell clients to cache for 5 years
			$seconds_to_cache = 60*60*24*365*5;
			$ts = gmdate("D, d M Y H:i:s", time() + $seconds_to_cache) . " GMT";
			header("Expires: $ts");
			header("Pragma: cache");
			header("Cache-Control: max-age=$seconds_to_cache");
			header('Pragma: public');
			
			// send image to client
			readfile($file);
			exit;
			
		}
		
		// error
		header ("HTTP/1.0 404 Not Found");
		exit;
	}
	
	$message = "";
	
	if (isset($_REQUEST['submit']) && isset($_FILES["the-file"])) {
	
		$file = $_FILES["the-file"];
		$message = "Missing or illegal file name!";
		
		if (isset($file["name"]) && isImageName($file["name"])) {
		
			$name = preg_replace('/^[.]|[^\w.]/i','-', $file['name']);
			$result = move_uploaded_file($file["tmp_name"],$dataDir.$name);
			$message = $result ? "The photo $name has been uploaded" :
				"Error receiving the file: $name";
		}
		header('Location: /');
		exit;
	}

	$images = array_filter(scandir($dataDir),'isImageName');

	if (isset($_REQUEST['delete-image'])) {
		$fileName = $_REQUEST['delete-image'];
		if (in_array($fileName,$images,true)) {
			unlink($dataDir.$fileName);
			header('Location: /');
			exit;
		}
	}
	
	echo $twig->render($fromPi ? 'list.twig' : 'index.twig', array(
		'title' => $title,
		'message' => $message,
		'images' => $images,
		'ui' => !$fromPi
	));

?>
