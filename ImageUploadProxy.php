<?php
/**
 * ImageUploadProxy.php - An object which exposes methods to facilitate the
 * upload of an image to a remote media server.
 *
 * Written by Will Hall (Oct 2019) during migration to a new media server.
 * */
class ImageUploadProxy{

	private $remoteBaseURL = "http://media2.medianet:8080/upload/";
	private $uploadUser = "";
	private $uploadPassword = "";
	
	function __construct(){
		//Env variables are defined in the Apache server block for this specific
		//VirtualHost (see /etc/apache2/sites-available).
		$this->uploadUser = getenv('MEDIA_UPLOAD_USER');
		$this->uploadPassword = getenv('MEDIA_UPLOAD_PASSWORD');
	}

	//Verifies that a file is an a valid image.
	public function verify($file){

		//Check if the file exists
		if($file['tmp_name'] === '') return "No file selected!";

		//Extract the file extension from the file name using pathinfo();
		$fileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

		//If the file type is not a valid image, disallow upload.
		if($fileType !== "jpg" &&
			   	$fileType !== "png" && 
				$fileType !== "gif" &&
			   	$fileType !== "jpeg"&&
				$fileType !== "bmp" &&
				$fileType !== "tiff") return "file type is invalid!"; 

		//If the file is greater than 100MB, disallow upload.
		if($file['size'] > (100 * 1024 * 1024) ) return "file is too large! Please select file under 100MB!";
		
		//If all tests pass, return null.
		return null;
	}
	
	//Uploads an image to the remote media server.
	public function upload($file, $filename, $targetGroup){

		//Verify that the file is valid
		$error = $this->verify($file);	

		//If the file is valid, continue.
		if(is_null($error)){

			//Ensure the filename and target group are specified.
			if(!empty($filename) && !empty($targetGroup)){

				//Construct the full upload URL, referencing the target group as
				//the destination for the resource.
				$url = $this->remoteBaseURL . $targetGroup;

				//We are POSTing an multipart form with the image data.
				$headers = array("Content-Type:multipart/form-data");

				//Construct the POST body. Use CurlFile to prepare the
				//temp cached file for upload.
				$postfields = array("resource" => new \CurlFile($file['tmp_name'], $file['type'], $file['name']),"filename"=>"$filename" );
				
				//Init the curl handle.
				$ch = curl_init();

				//Define an array of cURL options.
				$options = array(
					CURLOPT_URL => $url,
					CURLOPT_HEADER => true,
					CURLOPT_POST => 1,
					CURLOPT_TIMEOUT => 30,
					CURLOPT_HTTPHEADER => $headers,
					CURLOPT_POSTFIELDS => $postfields,
					CURLOPT_INFILESIZE => $file['size'],
					CURLOPT_RETURNTRANSFER => true		
				);

				//Set the cURL options, and set the auth headers.
				curl_setopt_array($ch, $options);
				curl_setopt($ch, CURLOPT_USERPWD, "$this->uploadUser:$this->uploadPassword");

				//Upload the image.
				curl_exec($ch);
				
				//If a critical error occurs, return a cURL error.
				if(curl_errno($ch)){
					return curl_error($ch);
				} else {

					//If the response code is 200, then the upload was
					//successful! Else, return an error with the HTTP response
					//code for debug.
					$code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
					if($code === 200){	   
						return null;
					} else {
						return "error: HTTP code: " . $code;
					}
				}

				//Close the cURL handle.
				curl_close($ch);

			} else {
				return "filename or target group undefined";
			}
		} else {
			return $error;
		}
	} 
}
?>
