<?php
require "vendor/autoload.php";

$config["VK_TOKEN_USER"]    = "";
$config["VK_TOKEN_GROUP"]   = "";
$config["VK_TOKEN_CONFIRM"] = "";
$config["VK_SECRET_KEY"]    = "";
$offset = 0;
$attachments = "";
$token_round = 0;

$lichi = new Lichi\VK\Callback($config);

$configData = file_get_contents("config.json");
$configArray = json_decode($configData, true);

if (is_null($configArray))
{
    throw new RuntimeException("Bad config file");
}
if (!isset($configArray['tokens']) || count($configArray['tokens']) == 0)
{
    throw new RuntimeException("Tokens from config.json NOT FOUND!");
}
if (!isset($configArray['text_messages']) || $configArray['text_messages'] == "")
{
    throw new RuntimeException("Text messages FROM config.json file NOT FOUND!");
}

$tokens = $configArray['tokens'];

$lichi->token_group = $tokens[$token_round]; 

if (isset($configArray['attachment_link']) && $configArray['attachment_link'] != "")
{
    $content = file_get_contents($configArray['attachment_link']);
    
    if(!$content)
    {
        throw new RuntimeException("Invalid URL attachment_link FROM config.json");
    }
    file_put_contents("image.jpg", $content);
    $attachments = $lichi->upload_photo("image.jpg");
}

$usersFromApi = $lichi->CallHowGroup("messages.getConversations", ['count'=>200]);
if (isset($usersFromApi['error'])){
    throw new RuntimeException($usersFromApi['error']['error_msg']);
}

$users = $usersFromApi["response"];
$count = $users["count"];

foreach ($users["items"] as $user)
{
    $usersList[] = $user["conversation"]["peer"]["id"]; 
}

if(count($usersList) > 150)
{
    while(count($users["items"]) > 0)
    {
        $offset += 200;
        $token_round++;
        if($token_round > count($tokens) - 1)
            $token_round = 0;
        $lichi->token_group = $tokens[$token_round]; 
        $usersArray = $lichi->CallHowGroup("messages.getConversations", ['count'=>200, 'offset'=>$offset]);
        $users = $usersArray["response"];
        foreach($users["items"] as $user)
        {
            $users_list[] = $user["conversation"]["peer"]["id"];
        }
        echo count($users_list)."/{$count}                      \r";
    }
}

if(count($users_list) > 100)
    $arr = array_chunk($usersList, 100);
else
    $arr = array_chunk($usersList, 10);

foreach($arr as $users){
    $token_round++;
    if($token_round > count($tokens) - 1)
            $token_round = 0;
    $lichi->token_group = $tokens[$token_round]; 
    $data = $lichi->CallHowGroup('messages.send', [
                                                    'message' => $configArray['text_messages'],
                                                    'user_ids'=>implode(",", $users),
                                                    'attachment'=> $attachments,
                                                    'random_id'=> rand(1,10000000)
                                                    ]);
    foreach($data["response"] as $user)
    {
        if(isset($user["error"]))
        {
            $token_round++;
            if($token_round > count($tokens) - 1)
                $token_round = 0;
            $lichi->token_group = $tokens[$token_round]; 
            $lichi->CallHowGroup("messages.deleteConversation", ['user_id'=>$user['peer_id']]);
        }
    }
}

?>
