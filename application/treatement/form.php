<?php

use \Respect\Validation\Validator as v;

$start = $_SERVER['REQUEST_URI'];
$explode_start = explode('-',$start);
$upload = false;

if(!empty($explode_start[1])){
    $start = \classified_ads\Ad::getAd($explode_start[1]);
}

if ($start == '/add_form' || !empty($start)) {
   
    $erreur = [];
    $new_category="";
    $extensionUpload_image = "";
    
    $user = new \classified_ads\User();
    $ad = new \classified_ads\Ad();
    
    //check email
    if (v::key('mail')->validate($_POST) && v::email()->validate($_POST['mail'])) {
        $user->mail = $_POST['mail'];
    } else {
        $erreur['mail'] = "Entrez un e-mail valide";
    }
    
    //check first name
    if(v::key('first_name')->validate($_POST) && v::alpha('-')->length(2,45)->validate($_POST['first_name'])){
        $user->firstName = $_POST['first_name'];
    } else {
        $erreur['first_name'] = "Entrez un prénom valide";
    }
    
    //check last name
    if(v::key('last_name')->validate($_POST) && v::alpha('-')->validate($_POST['last_name'])){
        $user->lastName = $_POST['last_name'];
    } else {
        $erreur['last_name'] = "Entrez un nom valide";
    }
    
    //check phone
    if(v::key('phone')->validate($_POST) && v::phone()->validate($_POST['phone'])) {
        $user->phone = $_POST['phone'];
    } else {
        $erreur['phone'] = "Entrez un numéro valide";
    }
    
    if(!\classified_ads\User::checkUser($user)) {
        $erreur['user'] = "Mauvais identifiant";
    }

    //check price
    if(v::notempty()->validate($_POST['price'])){
        if(v::key('price')->validate($_POST) && (v::numericVal()->positive()->validate($_POST['price'])) ){
            $ad->price = $_POST['price'];
        } else {
            $erreur['price'] = "Entrez un prix valide";
        } 
    }else{
        $ad->price = "";
    }
    
    
    //check category
    if(v::key('category')->validate($_POST) && v::digit()->validate($_POST['category'])){
        $ad->catId = $_POST['category'];
    } else{
        $erreur['category'] = "Categorie invalide";
    }
    
    //check title
    if(v::key('title')->validate($_POST) && v::notEmpty()->validate($_POST['title'])) {
        $ad->title = $_POST['title'];
    } else {
        $erreur['title'] = "Entrez un titre valide";
    }

    //check image
    if(v::key('img_url')->validate($_FILES) && v::notEmpty()->validate($_FILES['img_url']['name'])) {
        $extensions = array('jpg', 'jpeg', 'png');
        $extensionUpload_image = strtolower(substr(strrchr($_FILES['img_url']['name'], '.'), 1));
    
        if (!in_array($extensionUpload_image,$extensions)){
            $erreur['image'] = "Mauvaise extension de fichier";
        }else{
            $upload = true;
        }
    }else if(explode('-',$_SERVER['REQUEST_URI'])[0]=="/edit"){
        $ad->imgUrl = \classified_ads\Ad::getAd(explode('-',$_SERVER['REQUEST_URI'])[1])['a_img_url'];
    }else{
        $ad->imgUrl = "assets/medias/default.jpeg";
    }
    
    //check desc
    if(v::key('desc')->validate($_POST) && v::notEmpty()->length(5,255)->validate($_POST['desc'])) {
        $ad->description = $_POST['desc'];
    } else {
        $erreur['desc'] = "Entrez une description valide";
    }
    
    // //check captcha.
    // // your secret key
    // $secret = CAPTCHA_PRIVATE_KEY;
    // // empty response
    // $response = null;
    // // check secret key 
    // $reCaptcha = new \classified_ads\ReCaptcha($secret);
    
    // // if submitted check response
    // if ($_POST["g-recaptcha-response"]) {
    //     $response = $reCaptcha->verifyResponse(
    //         $_SERVER["REMOTE_ADDR"],
    //         $_POST["g-recaptcha-response"]
    //     );
    // }
    
    // if ($response == null || ($response->success==false)) {
    //     $erreur['captcha'] = 'Merci de cocher le captcha';
    // }
    
    /** 
     * Fin des tests
     */
    if(empty($erreur)) {
        $user->getUserId();
        $ad->userId = $user->id;
        $ad->setId();
        
        //on crée le repertoire de l'utilisateur s'il n'existe pas
        if(!file_exists("assets/medias/$user->id")) {
            mkdir("assets/medias/$user->id");
        }

        //on upload l'image
        if($upload) {
            $ad->imgUrl = "assets/medias/$user->id/$ad->id.$extensionUpload_image";
            move_uploaded_file($_FILES['img_url']['tmp_name'], $ad->imgUrl);
        }

        if(explode('-',$_SERVER['REQUEST_URI'])[0]=="/add_form"){

            //on crée l'id unique
            $mail_crypt = \classified_ads\Crypt::encryptSimple($user->mail);
            
            $ad->uniqueId = "defaultId";

            //on crée le fichier pdf de l'annonce
            $ad->makePdf();
            
            //ajout l'annonce dans la bdd
            \classified_ads\Ad::add($ad);

            $id_crypt = \classified_ads\Crypt::encryptSimple(\classified_ads\Ad::getId($ad->uniqueId));
            
            \classified_ads\Ad::updateId($ad->uniqueId,hash('sha1',"$mail_crypt&$id_crypt"));
    
            //envoi mail de confirmation
            $sujet = "Confirmez votre annonce : $ad->title";
            $message = "Pour confirmer votre annonce, cliquez sur le lien suivant <a href = '".SERVER_URI."/confirm-$mail_crypt&$id_crypt'>Confirmation</a>.<br>
            Vous pouvez toujours l'éditer en cliquant <a href = '".SERVER_URI."/edit-$mail_crypt&$id_crypt'>ici</a> !<br><br>".$ad->recap();
            
            if(\classified_ads\Mail::mailTo($user,$sujet,$message)) {
                $erreur['mail'] = "Un email à été envoyé";
            }else{
                $erreur['mail'] = "L'envoi d'email à échoué";
            }

        }else if(explode('-',$_SERVER['REQUEST_URI'])[0]=="/edit"){
            \classified_ads\Ad::modify($ad);
        }
    }
    // json_encode($erreur);
}else{
    header('Location: /add_ad');
}