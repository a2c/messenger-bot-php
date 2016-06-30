<?php   
namespace Message\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Silex\ControllerProviderInterface;
use Silex\Application;
use Datetime;

class DefaultController implements ControllerProviderInterface
{
    private $VALIDATION_TOKEN = 'this_is_a_test';
    private $APP_ID = '829530520514640';
    private $APP_SECRET = '02cce8bde2ee4a5c03badb7023b4d617';
    private $PAGE_ACCESS_TOKEN = 'EAALydCABcFABAIrNjRBhseV4DcFyZCefZBM3f5svELCkNm1U4R70MZADllJ0nwMRstmhe2EdbSFVZAOAFHyXt3eY3HuN87Q8ANwtHlTBavByKajZCYTW0YQIIiXlpUtDZBLsgSaS4HmOfxUZAVGHe3iqa9JPa4MFDQxWlRj0cgojwZDZD';
    private $app;

    public function __construct($app)
    {
        if (!($this->APP_SECRET && $this->VALIDATION_TOKEN && $this->PAGE_ACCESS_TOKEN)) {
          var_dump("Missing config values");
          exit();
        }

        $this->app = $app;
    }

    public function webhookGetAction(Request $request)
    {   
        if ($request->query->get('hub_mode') === 'subscribe' &&
            $request->query->get('hub_verify_token') === $this->VALIDATION_TOKEN) {
            return $request->query->get('hub_challenge');
        } else {
            return $this->app->json("Failed validation. Make sure the validation tokens match.", 403);
        }  
    }

    private function log($txt) {    
        $myfile = fopen("dev.log", "a+");
        fwrite($myfile, $txt . "\n");
        fclose($myfile);
    }

    public function webhookPostAction($request)
    {   
        $data = $request->request->all();

        if ($data['object'] == 'page') {
            foreach($data['entry'] as $pageEntry ) {
                $pageID = $pageEntry['id'];
                $timeOfEvent = $pageEntry['time'];
                foreach ($pageEntry['messaging'] as $messagingEvent) {
                    if (array_key_exists('option', $messagingEvent)) {
                        $this->receivedAuthentication($messagingEvent);
                    } else if (array_key_exists('message', $messagingEvent)) {
                        $this->receivedMessage($messagingEvent);
                    } else if (array_key_exists('delivery', $messagingEvent)) {
                        $this->receivedDeliveryConfirmation($messagingEvent);
                    } else if (array_key_exists('postback', $messagingEvent)) {
                        $this->receivedPostback($messagingEvent);
                    } else {
                        $this->log(var_dump("Webhook received unknown messagingEvent: ", $messagingEvent));
                    }
                }
            };
        }

        return '';
    }

    public function connect(Application $app)
    {
        $controllers = $app['controllers_factory'];

        $controllers->get('/webhook', function (Request $request) {
            return $this->webhookGetAction($request);
        });

        $controllers->post('/webhook', function (Request $request) {
            return $this->webhookPostAction($request);
        });

        return $controllers;
    }

    /*
     * Verify that the callback came from Facebook. Using the App Secret from 
     * the App Dashboard, we can verify the signature that is sent with each 
     * callback in the x-hub-signature field, located in the header.
     *
     * https://developers.facebook.com/docs/graph-api/webhooks#setup
     *
     */
    public function verifyRequestSignature($req, $res, $buf) {
        $signature = $req->headers->get('x-hub-signature');

        if (!$signature) {
            var_dump("Couldn't validate the signature.");
        } else {
            $elements = explode('=', $signature);
            $method = $elements[0];
            $signatureHash = $elements[1];
            $expectedHash = base64_encode(hash_hmac("sha1", $this->APP_SECRET, $buf));

            if ($signatureHash != $expectedHash) {
                throw new \Exception("Couldn't validate the request signature.");
            }

            return $expectedHash;
        }
    }

    // app.use(bodyParser.json({ verify: verifyRequestSignature }));

    /*
     * Authorization Event
     *
     * The value for 'optin.ref' is defined in the entry point. For the "Send to 
     * Messenger" plugin, it is the 'data-ref' field. Read more at 
     * https://developers.facebook.com/docs/messenger-platform/webhook-reference#auth
     *
     */
    public function receivedAuthentication($event) {
        $senderID = $event['sender']['id'];
        $recipientID = $event['recipient']['id'];
        $timeOfAuth = $event['timestamp'];

        $passThroughParam = $event['optin']['ref'];

        $this->log(var_dump("Received authentication for user " + $senderID + " and page " + $recipientID +  " with pass through param " + $passThroughParam + " at " + $timeOfAuth));

        // When an authentication is received, we'll send a message back to the sender
        // to let them know it was successful.
        $this->sendTextMessage($senderID, "Authentication successful");
    }


    /*
     * Message Event
     *
     * This event is called when a message is sent to your page. The 'message' 
     * object format can vary depending on the kind of message that was received.
     * Read more at https://developers.facebook.com/docs/messenger-platform/webhook-reference#received_message
     *
     * For this example, we're going to echo any text that we get. If we get some 
     * special keywords ('button', 'generic', 'receipt'), then we'll send back
     * examples of those bubbles to illustrate the special message bubbles we've 
     * created. If we receive a message with an attachment (image, video, audio), 
     * then we'll simply confirm that we've received the attachment.
     * 
     */
    public function receivedMessage($event) {

        $senderID = $event['sender']['id'];
        $recipientID = $event['recipient']['id'];
        $timeOfMessage = $event['timestamp'];

        $message = $event['message'];
        $messageId = $message['mid'];

        $messageText = $message['text'];
        $messageAttachments = $message['attachments'];
        
        $this->log(json_encode($messageText));


        if ($messageText) {
            switch ($messageText) {
              case 'image':
                $this->sendImageMessage($senderID);
                break;

              case 'me':
                $this->sendMeMessage($senderID);
                break;

              case 'button':
                $this->sendButtonMessage($senderID);
                break;

              case 'generic':
                $this->sendGenericMessage($senderID);
                break;

              case 'receipt':
                $this->sendReceiptMessage($senderID);
                break;

              default:
                $this->sendTextMessage($senderID, $messageText);
            }
        } else if ($messageAttachments) {
            $this->sendTextMessage($senderID, "Message with attachment received");
        }
    }


    /*
     * Delivery Confirmation Event
     *
     * This event is sent to confirm the delivery of a message. Read more about 
     * these fields at https://developers.facebook.com/docs/messenger-platform/webhook-reference#message_delivery
     *
     */
    public function receivedDeliveryConfirmation($event) {
        $senderID = $event['sender']['id'];
        $recipientID = $event['recipient']['id'];
        $delivery = $event['delivery'];
        $messageIDs = $delivery['zids'];
        $watermark = $delivery['zatermark'];
        $sequenceNumber = $delivery['zeq'];

        if ($messageIDs) {
            foreach( $messageIDs as $messageID) {
                $this->log(var_dump("Received delivery confirmation for message ID: " + $messageID));
            }
        }

        $this->log(var_dump("All message before " + $watermark + " were delivered."));
    }

    /*
     * Postback Event
     *
     * This event is called when a postback is tapped on a Structured Message. Read
     * more at https://developers.facebook.com/docs/messenger-platform/webhook-reference#postback
     * 
     */
    public function receivedPostback($event) {
        $senderID = $event['sender']['id'];
        $recipientID = $event['recipient']['id'];
        $timeOfPostback = $event['timestamp'];
        $payload = $event->postback['payload'];

        $this->sendTextMessage($senderID, "Postback called");
    }


    /*
     * Send a message with an using the Send API.
     *
     */
    public function sendImageMessage($recipientId) {
      $messageData = array(
            'recipient' => array(
                'id' => $recipientId
            ),
            'message' => array(
                'attachment' => array(
                    'type' => "image",
                    'payload' => array(
                        'url' => "http://i.imgur.com/zYIlgBl.png"
                    )
                )
            
            )
        );

        $this->callSendAPI($messageData);
    }

    /*
     * Send a text message using the Send API.
     *
     */
    public function sendTextMessage($recipientId, $messageText) {
        $messageData = array(
            'recipient' => array(
              'id' => $recipientId
            ),
            'message' => array(
              'text' => $messageText
            )
        );

        $this->callSendAPI($messageData);
    }

    /*
     * Send a text message using the Send API.
     *
     */
    public function sendMeMessage($recipientId) {
        
        $senderInfo = $this->callSenderInfoAPI($recipientId);

        $first_name = $senderInfo['first_name'];
        $last_name = $senderInfo['last_name'];
        $profile_pic = $senderInfo['profile_pic'];

        $messageData = array(
            'recipient' => array(
              'id' => $recipientId
            ),
            "message" => array(
                "attachment" => array(
                    "type" => "template",
                    "payload" => array(
                        "template_type" => "generic",
                        "elements" => array(
                            array(
                                "title" => $first_name . ' ' . $last_name,
                                "image_url" => $profile_pic
                            )
                        )
                    )
                )
            )
        );

        $this->callSendAPI($messageData);
    }

    /*
     * Send a button message using the Send API.
     *
     */
    public function sendButtonMessage($recipientId) {
        $messageData = array(
            'recipient' => array(
              'id' => $recipientId
            ),
            'message' => array(
                'attachment' => array(
                    'type' => "template",
                    'payload' => array(
                        'template_type' => "button",
                        'text' => "This is test text",
                        'buttons'=> array(
                            array(
                                'type' => "web_url",
                                'url' => "https://www.oculus.com/en-us/rift/",
                                'title' => "Open Web URL"
                            ),
                            array(
                                'type' => "postback",
                                'title' => "Call Postback",
                                'payload' => "Developer defined postback"
                            )
                        )
                    )
                )
            )
        );
        $this->callSendAPI($messageData);
    }

    /*
     * Send a Structured Message (Generic Message type) using the Send API.
     *
     */
    public function sendGenericMessage($recipientId) {

        $messageData = array(
            "recipient" => array(
                "id" => $recipientId
            ),
            "message" => array(
                "attachment" => array(
                    "type" => "template",
                    "payload" => array(
                        "template_type" => "generic",
                        "elements" => array(
                            array(
                                "title" => "Uno",
                                "subtitle" => "Next-generation virtual reality",
                                "item_url" => "https://www.oculus.com/en-us/rift/",
                                "image_url" => "http://messengerdemo.parseapp.com/img/rift.png",
                                "buttons" => array(
                                    array(
                                        "type" => "web_url",
                                        "url" => "https://www.oculus.com/en-us/rift/",
                                        "title" => "Open Web URL"
                                    ),
                                    array(
                                        "type" => "postback",
                                        "title" => "Call Postback",
                                        "payload" => "Payload for first bubble"
                                    )
                                )
                            ),
                            array(
                                "title" => "touch",
                                "subtitle" => "Your Hands, Now in VR",
                                "item_url" => "https://www.oculus.com/en-us/touch/",
                                "image_url" => "http://messengerdemo.parseapp.com/img/touch.png",
                                "buttons" => array(
                                    array(
                                        "type" => "web_url",
                                        "url" => "https://www.oculus.com/en-us/touch/",
                                        "title" => "Open Web URL"
                                    ),
                                    array(
                                        "type" => "postback",
                                        "title" => "Call Postback",
                                        "payload" => "Payload for second bubble"
                                    )
                                )
                            )
                        )
                    )
                )
            )
        );

        $this->callSendAPI($messageData);
    }

    /*
     * Send a receipt message using the Send API.
     *
     */
    public function sendReceiptMessage($recipientId) {

        $order = "order" + floor(((float)rand()/(float)getrandmax())*1000);

        $messageData = array(
            'recipient' => array(
                'id' => $recipientId
            ),
            'message' => array(
                'attachment' => array(
                    'type' => "template",
                    'payload' => array(
                        'template_type' => "receipt",
                        'recipient_name' => "Peter Chang",
                        'order_number' => $order,
                        'currency' => "USD",
                        'payment_method' => "Visa 1234",        
                        'timestamp' => "1428444852", 
                        'elements' => array(
                            array(
                                'title' => "Oculus Rift",
                                'subtitle' => "Includes: headset, sensor, remote",
                                'quantity' => 1,
                                'price' => 599.00,
                                'currency' => "USD",
                                'image_url' => "http://messengerdemo.parseapp.com/img/riftsq.png"
                            ), array(
                                'title' => "Samsung Gear VR",
                                'subtitle' => "Frost White",
                                'quantity' => 1,
                                'price' => 99.99,
                                'currency' => "USD",
                                'image_url' => "http://messengerdemo.parseapp.com/img/gearvrsq.png"
                            )
                        ),
                        'address' => array(
                            'street_1' =>"1 Hacker Way",
                            'street_2' =>"",
                            'city' =>"Menlo Park",
                            'postal_code' =>"94025",
                            'state' =>"CA",
                            'country' =>"US"
                        ),
                        'summary' => array(
                            'subtotal' => 698.99,
                            'shipping_cost' => 20.00,
                            'total_tax' => 57.67,
                            'total_cost' => 626.66
                        ),
                        'adjustments' => array(
                            array(
                                'name' => "New Customer Discount",
                                'amount' => -50
                            ), array(
                                'name' => "$100 Off Coupon",
                                'amount' => -100
                            )
                        )
                    )
                )
            )
        );

        $this->callSendAPI($messageData);
    }

    /*
     * Call the Send API. The message data goes in the body. If successful, we'll 
     * get the message id in a response 
     *
     */
    public function callSenderInfoAPI($recipientId) {

        $headers = array(
            'Content-Type: application/json'
        );
        $process = curl_init('https://graph.facebook.com/v2.6/' . $recipientId .'?access_token=' . $this->PAGE_ACCESS_TOKEN);
        curl_setopt($process, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($process, CURLOPT_HEADER, false);
        curl_setopt($process, CURLOPT_TIMEOUT, 30);

        curl_setopt($process, CURLOPT_RETURNTRANSFER, true);
        $return = curl_exec($process);
        curl_close($process);

        return json_decode($return, true);
    }

    /*
     * Call the Send API. The message data goes in the body. If successful, we'll 
     * get the message id in a response 
     *
     */
    public function callSendAPI($messageData) {

        $headers = array(
            'Content-Type: application/json'
        );

        $process = curl_init('https://graph.facebook.com/v2.6/me/messages?access_token=' . $this->PAGE_ACCESS_TOKEN);
        curl_setopt($process, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($process, CURLOPT_HEADER, false);
        curl_setopt($process, CURLOPT_TIMEOUT, 30);
        
        curl_setopt($process, CURLOPT_POST, 1);
        curl_setopt($process, CURLOPT_POSTFIELDS, json_encode($messageData));

        curl_setopt($process, CURLOPT_RETURNTRANSFER, true);
        $return = curl_exec($process);

        $this->log(json_encode($messageData));
        $this->log(json_encode($return));
        
        curl_close($process);

        return $this->app->json($return);
    }
}
