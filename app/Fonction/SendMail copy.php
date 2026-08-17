<?php 

namespace App\Fonction;
use \Mailjet\Resources;
use Mailjet\LaravelMailjet\Facades\Mailjet;

class SendMail
{

    public function send($mail_user,$name_user,$subject,$textpart,$view)
    {
       
       /* MAILJETAPIKEY=e146a234e46f999be3f3a8c1cc4aae15
MAILJETAPISECRET="690eb89d81443afe25894b15cce1871c"*/
//$mailjet = new \Mailjet\Client('03a26c469db3e656271c7db1200c3765','823e435b6b520da79a457cbf4c141253',true,['version' => 'v3.1']);
// $mailjet = new \Mailjet\Client('9455c2db1879bbaf46a5d0bbc15bdc52','92ef413e38d69800cb24d537694d4809',true,['version' => 'v3.1']);
$mailjet = new \Mailjet\Client('92ef413e38d69800cb24d537694d4809','9455c2db1879bbaf46a5d0bbc15bdc52',true,['version' => 'v3.1']);

     $body = [
    'Messages' => [
        [
            'From' => [
                'Email' => "infos@pouletafc.com",
                'Name' => "Poulet AFC"
            ],
            'To' => [
                [
                    'Email' => $mail_user,
                    'Name' => $name_user
                ]
            ],
            'Subject' => $subject,
            'TextPart' => $textpart,
            'HTMLPart' => 'Chers M./Mme  '.$name_user.' '.$view
        ]
    ]
];
$response = $mailjet->post(Resources::$Email, ['body' => $body]);
// if($response->success())
//  return 'Success'; 
// else return 'Failed';
    }
}




















?>