<?php
    function validate_name(string $name): void {
        if (!preg_match("/^[a-zA-Z- ]{3,50}$/", $name)) {
            api_error("Name length should be 3-50 characters and only contain alphabets with whitespaces in between.");
        }
    }

    function validate_email(string $email): void {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            api_error("Please enter a valid email address");
        }

        $at = strrpos($email, '@');
        $domain = $at !== false ? strtolower(substr($email, $at + 1)) : '';

        if($domain === '' || str_contains($domain, '..') || str_contains($domain, '.com.') || str_ends_with($domain, '.com.com') || preg_match('/(\.[a-z]{2,})\1$/i', $email)){
            api_error("Please enter a valid email address");
        }
    }

    function validate_mobile(string $mobile): void{
        $mobile = preg_replace('/\s+|-/', '', $mobile);
        if (!preg_match('/^(?:\+91|91)?[6-9]\d{9}$/', $mobile)) {
            api_error("Invalid mobile number");
        }
    }

    function validate_password(string $password): void {
        if (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d])[^\s]{8,}$/", $password)){
            api_error("Use 8 characters with one uppercase, one lowercase, one number, and one special character.");
        }
    }

    // function validate_post_images(array $files): void{
    //     if(empty($files) || !isset($files['name']) || empty($files['name'][0])){
    //         api_error("At least one image is required");
    //     }

    //     $count = count($files['name']);
    //     if($count > 5){
    //         api_error("You can upload a maximum of 5 images");
    //     }

    //     $allowedMime = ['image/jpeg', 'image/png'];
    //     $allowedExt = ['jpg', 'jpeg', 'png'];
    //     $maxSize = 2*1024*1024;

    //     for($i=0;$i<$count;$i++){
    //         if($files['error'][$i] !== UPLOAD_ERR_OK){
    //             api_error("Image upload error");
    //         }

    //         $tmp = $files['tmp_name'][$i];
    //         $size = (int)$files['size'][$i];
    //         $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
    //         $mime = mime_content_type($tmp);

    //         if(!is_uploaded_file($tmp)){
    //             api_error("Invalid image upload detected");
    //         }

    //         if(!getimagesize($tmp)){
    //             api_error("Invalid image file");
    //         }

    //         if(!in_array($mime, $allowedMime, true)){
    //             api_error("Only JPG and PNG images are allowed");
    //         }

    //         if(!in_array($ext, $allowedExt, true)){
    //             api_error("Invalid image extension");
    //         }

    //         if($size > 2*1024*1024){
    //             api_error("Each image must be less than 2MB");
    //         }
    //     }
    // }


    function validate_post_images(array $files): void{
        if(empty($files) || empty($files['name'])){
            api_error("At least one image is required");
        }

        if(!is_array($files['name'])){
            foreach($files as $key => $value){
                $files[$key] = [$value];
            }
        }
        if (empty($files['name'][0]) || (count($files['error']) === 1 && $files['error'][0] === UPLOAD_ERR_NO_FILE)){
            api_error("At least one image is required");
        }

        $count = count($files['name']);
        if($count < 1){
            api_error("At least one image is required");
        }
        if($count > 5){
            api_error("You can upload a maximum of 5 images");
        }

        $allowedMime = ['image/jpeg', 'image/png'];
        $allowedExt = ['jpg', 'jpeg', 'png'];
        $maxSize = 2*1024*1024;

        for ($i=0;$i<$count;$i++){
            if($files['error'][$i] !== UPLOAD_ERR_OK){
                api_error("Image upload error");
            }

            $tmp = $files['tmp_name'][$i];
            $size = (int)$files['size'][$i];
            $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
            $mime = mime_content_type($tmp);

            if(!is_uploaded_file($tmp)){
                api_error("Invalid image upload detected");
            }

            if(!getimagesize($tmp)){
                api_error("Invalid image file");
            }

            if(!in_array($mime, $allowedMime, true)){
                api_error("Only JPG and PNG images are allowed");
            }

            if(!in_array($ext, $allowedExt, true)){
                api_error("Invalid image extension");
            }

            if($size > $maxSize){
                api_error("Each image must be less than 2MB");
            }
        }
    }
?>