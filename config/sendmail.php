<?php
/*
this class encapsulates the PHP mail() function.
 implements CC, Bcc, Priority headers


@version        1.3

- added ReplyTo( $address ) method
- added Receipt() method - to add a mail receipt
- added optionnal charset parameter to Body() method. this should fix charset problem on some mail clients

@example

        $m= new Mail; // create the mail
        $m->From( "leo@isp.com" );
        $m->To( "destination@somewhere.fr" );
        $m->Subject( "the subject of the mail" );

        $message= "Hello world!\nthis is a test of the Mail class\nplease ignore\nThanks.";
        $m->Body( $message);        // set the body
        $m->Cc( "someone@somewhere.fr");
        $m->Bcc( "someoneelse@somewhere.fr");
        $m->Priority(4) ;        // set the priority to Low
        $m->Attach( "/home/leo/toto.gif", "image/gif" ) ;        // attach a file of type image/gif

        //alternatively u can get the attachment uploaded from a form
        //and retreive the filename and filetype and pass it to attach methos

        $m->Send();        // send the mail
        echo "the mail below has been sent:<br><pre>", $m->Get(), "</pre>";



@author     Saravanan(winsaravanan@yahoo.com,ssaravanan@teledata-usa.com)

*/


class Mail
{
        /*
        list of To addresses
        @var        array
        */
        var $sendto = array();
        /*
        @var        array
        */
        var $acc = array();
        /*
        @var        array
        */
        var $abcc = array();
        /*
        paths of attached files
        @var array
        */
        var $aattach = array();
        /*
        list of message headers
        @var array
        */
        var $xheaders = array();
        /*
        message priorities referential
        @var array
        */
        var $priorities = array( '1 (Highest)', '2 (High)', '3 (Normal)', '4 (Low)', '5 (Lowest)' );
        /*
        character set of message
        @var string
        */
        var $charset = "us-ascii";
        var $ctencoding = "7bit";
        var $receipt = 0;
        var $content_type='';
		
		var $SmtpServer = "localhost";
		var $PortSMTP = "25";
		var $SmtpUser = "";
		var $SmtpPass ="";

/*

        Mail contructor

*/
function __construct()
{
	$this->autoCheck( true );
    $this->boundary= "--" . md5( uniqid("myboundary") );
}

function __destruct()
{
	unset($this->sendto);
	unset($this->acc);
	unset($this->abcc);
	unset($this->aattach);
	unset($this->xheaders);
}
function Mail()
{
        $this->autoCheck( true );
        $this->boundary= "--" . md5( uniqid("myboundary") );
}


function Content_type($contenttype){

    $this->content_type=$contenttype;
    //echo $this->content_type;
    //echo '<br>';
    //exit();
}

/*

activate or desactivate the email addresses validator
ex: autoCheck( true ) turn the validator on
by default autoCheck feature is on

@param boolean        $bool set to true to turn on the auto validation
@access public
*/
function autoCheck( $bool )
{
        if( $bool )
                $this->checkAddress = true;
        else
                $this->checkAddress = false;
}


/*

Define the subject line of the email
@param string $subject any monoline string

*/
function Subject( $subject )
{
        $this->xheaders['Subject'] = strtr( $subject, "\r\n" , "  " );
}


/*

set the sender of the mail
@param string $from should be an email address

*/

function From( $from )
{

        if( ! is_string($from) ) {
                echo "Class Mail: error, From is not a string";
                exit;
        }
        $this->xheaders['From'] = $from;
}

/*
 set the Reply-to header
 @param string $email should be an email address

*/
function ReplyTo( $address )
{

        if( ! is_string($address) )
                return false;

        $this->xheaders["Reply-To"] = $address;

}


/*
add a receipt to the mail ie.  a confirmation is returned to the "From" address (or "ReplyTo" if defined)
when the receiver opens the message.

@warning this functionality is *not* a standard, thus only some mail clients are compliants.

*/

function Receipt()
{
        $this->receipt = 1;
}


/*
set the mail recipient
@param string $to email address, accept both a single address or an array of addresses

*/

function To( $to )
{

        // TODO : test validit� sur to
        if( is_array( $to ) )
                $this->sendto= $to;
        else
                $this->sendto[] = $to;

        if( $this->checkAddress == true )
                $this->CheckAdresses( $this->sendto );

}


/*                Cc()
 *                set the CC headers ( carbon copy )
 *                $cc : email address(es), accept both array and string
 */

function Cc( $cc )
{
        if( is_array($cc) )
                $this->acc= $cc;
        else
                $this->acc[]= $cc;

        if( $this->checkAddress == true )
                $this->CheckAdresses( $this->acc );

}



/*                Bcc()
 *                set the Bcc headers ( blank carbon copy ).
 *                $bcc : email address(es), accept both array and string
 */

function Bcc( $bcc )
{
        if( is_array($bcc) ) {
                $this->abcc = $bcc;
        } else {
                $this->abcc[]= $bcc;
        }

        if( $this->checkAddress == true )
                $this->CheckAdresses( $this->abcc );
}


/*                Body( text [, charset] )
 *                set the body (message) of the mail
 *                define the charset if the message contains extended characters (accents)
 *                default to us-ascii
 *                $mail->Body( "m�l en fran�ais avec des accents", "iso-8859-1" );
 */
function Body( $body, $charset="" )
{
        $this->body = $body;

        if( $charset != "" ) {
                $this->charset = strtolower($charset);
                if( $this->charset != "us-ascii" )
                        $this->ctencoding = "8bit";
        }
}


/*                Organization( $org )
 *                set the Organization header
 */

function Organization( $org )
{
        if( trim( $org != "" )  )
                $this->xheaders['Organization'] = $org;
}


/*                Priority( $priority )
 *                set the mail priority
 *                $priority : integer taken between 1 (highest) and 5 ( lowest )
 *                ex: $mail->Priority(1) ; => Highest
 */

function Priority( $priority )
{
        if( ! intval( $priority ) )
                return false;

        if( ! isset( $this->priorities[$priority-1]) )
                return false;

        $this->xheaders["X-Priority"] = $this->priorities[$priority-1];

        return true;

}


/*
 Attach a file to the mail

 @param string $filename : path of the file to attach
 @param string $filetype : MIME-type of the file. default to 'application/x-unknown-content-type'
 @param string $disposition : instruct the Mailclient to display the file if possible ("inline") or always as a link ("attachment") possible values are "inline", "attachment"
 */

function Attach($filename,$filetype = "",$disposition = "inline")
{

        if( $filetype == "" )
                $filetype = "application/x-unknown-content-type";
                //$filetype = "text/plain";



         $this->aattach[] = $filename;


        $this->actype[] = $filetype;
        $this->adispo[] = $disposition;

}

/*

Build the email message

@access protected

*/
function BuildMail()
{

        // build the headers
        $this->headers = "";
//        $this->xheaders['To'] = implode( ", ", $this->sendto );

        if( count($this->acc) > 0 )
                $this->xheaders['CC'] = implode( ", ", $this->acc );

        if( count($this->abcc) > 0 )
                $this->xheaders['BCC'] = implode( ", ", $this->abcc );


        if( $this->receipt ) {
                if( isset($this->xheaders["Reply-To"] ) )
                        $this->xheaders["Disposition-Notification-To"] = $this->xheaders["Reply-To"];
                else
                        $this->xheaders["Disposition-Notification-To"] = $this->xheaders['From'];
        }

        if( $this->charset != "" ) {
                //global $contenttype;
                $content_type=$this->content_type;
                $this->xheaders["Mime-Version"] = "1.0";
                $this->xheaders["Content-Type"] = "$content_type; charset=$this->charset";
                $this->xheaders["Content-Transfer-Encoding"] = $this->ctencoding;
        }

        $this->xheaders["X-Mailer"] = "RLSP Mailer";

        // include attached files
        if( count( $this->aattach ) > 0 ) {

                $this->_build_attachement();
        } else {
                $this->fullBody = $this->body;
        }

        reset($this->xheaders);
        while( list( $hdr,$value ) = each( $this->xheaders )  ) {
                if( $hdr != "Subject" )
                        $this->headers .= "$hdr: $value\n";
        }


}
/* send mail using smtp*/
function BuildMail_SMTP(){
$this->BuildMail();
 
	$this->strTo = implode( ", ", $this->sendto );
	$this->SmtpUser = base64_encode($this->SmtpUser);
	$this->SmtpPass = base64_encode($this->SmtpPass);
	
	$newLine = "\r\n";

	if ($SMTPIN = fsockopen ($this->SmtpServer, $this->PortSMTP))
	{
		/*$server_host = $_SERVER['HTTP_HOST'];
		fputs ($SMTPIN, "EHLO ".$server_host."\r\n");
		$talk["hello"] = fgets ( $SMTPIN, 1024 );*/
		
		
		fputs($SMTPIN, "auth login\r\n");
		$talk["res"]=fgets($SMTPIN,1024);
		fputs($SMTPIN, $this->SmtpUser."\r\n");
		$talk["user"]=fgets($SMTPIN,1024);
		fputs($SMTPIN, $this->SmtpPass."\r\n");
		$talk["pass"]=fgets($SMTPIN,256);
		fputs ($SMTPIN, "MAIL FROM: <".$this->xheaders['From'].">\r\n");
		$talk["From"] = fgets ( $SMTPIN, 1024 );
		fputs ($SMTPIN, "RCPT TO: <".$this->strTo.">\r\n");
		$talk["To"] = fgets ($SMTPIN, 1024);
		fputs($SMTPIN, "DATA\r\n");
		$talk["data"]=fgets( $SMTPIN,1024 );
		
		$headers  = "MIME-Version: 1.0" ;
		$headers .= "\r\nContent-type: text/html; charset=iso-8859-1";
		
		
		/*fputs($SMTPIN, "To: <".$this->strTo.">\r\nFrom: <".$this->xheaders['From'].">\r\nSubject:".$this->xheaders['Subject']."\r\nMIME-Version: 1.0\r\nContent-type: text/html; charset=iso-8859-1\r\n\r\n\r\n".$this->fullBody."\r\n.\r\n");
		$talk["send"]=fgets($SMTPIN,256);*/
		
		fputs($SMTPIN, "To: <".$this->strTo.">\r\nSubject:".$this->xheaders['Subject']."\r\nReply-to:".$this->xheaders['Reply-To']."\r\nMIME-Version: 1.0\r\nContent-type: text/html; charset=iso-8859-1\r\n\r\n\r\n".$this->fullBody."\r\n.\r\n");
		$talk["send"]=fgets($SMTPIN,256);
		//CLOSE CONNECTION AND EXIT ...
		fputs ($SMTPIN, "QUIT\r\n");
		fclose($SMTPIN);
		//
		
	}
	
	return $talk;
}

/*
        fornat and send the mail
        @access public
*/

function Send()
{
        //global $filename;

        $this->BuildMail();

        $this->strTo = implode( ", ", $this->sendto );

	//echo $this->xheaders['Subject'];
	//echo $this->fullBody;
        $res = @mail( $this->strTo, $this->xheaders['Subject'], $this->fullBody, $this->headers );
		unset($this->sendto);
		unset($this->acc);
		unset($this->abcc);
		unset($this->aattach);
		unset($this->xheaders);
		
		return $res;
}



/*
 *                return the whole e-mail , headers + message
 *                can be used for displaying the message in plain text or logging it
 */

function Get()
{
        $this->BuildMail();
        $mail = "To: " . $this->strTo . "\n";
        $mail .= $this->headers . "\n";
        $mail .= $this->fullBody;
        return $mail;
}


/*
        check an email address validity
        @access public
        @param string $address : email address to check
        @return true if email adress is ok
 */

function ValidEmail($address)
{
        if( ereg( ".*<(.+)>", $address, $regs ) ) {
                $address = $regs[1];
        }
         if(ereg( "^[^@  ]+@([a-zA-Z0-9\-]+\.)+([a-zA-Z0-9\-]{2}|net|com|gov|mil|org|edu|int)\$",$address) )
                 return true;
         else
                 return false;
}


/*

        check validity of email addresses
        @param        array $aad -
        @return if unvalid, output an error message and exit, this may -should- be customized

 */

function CheckAdresses( $aad )
{
        for($i=0;$i< count( $aad); $i++ ) {
				if( ! $this->ValidEmail( $aad[$i]) ) {
                        echo "Class Mail, method Mail : invalid address $aad[$i]";
                        return "Invalid address $aad[$i]";
						exit; // omited the comments
                }
        }
}


/*
 check and encode attach file(s) . internal use only

*/

function _build_attachement()
{

        $this->xheaders["Content-Type"] = "multipart/mixed;\n boundary=\"$this->boundary\"";

        $this->fullBody = "This is a multi-part message in MIME format.\n--$this->boundary\n";
        $this->fullBody .= "Content-Type: text/html; charset=$this->charset\nContent-Transfer-Encoding: $this->ctencoding\n\n" . $this->body ."\n";

        $sep= chr(13) . chr(10);

        $ata= array();
        $k=0;
        // for each attached file, do...
        for( $i=0; $i < count( $this->aattach); $i++ ) {

                $filename = $this->aattach[$i];
                $basename = basename($filename);
                $ctype = $this->actype[$i];        // content-type
                $disposition = $this->adispo[$i];
                /*getting the original name of the file */

                //echo $original_filename;

                if( ! file_exists( $filename) ) {
                        echo "Class Mail, method attach : file $filename can't be found"; exit;
                }

               /* echo 'filename--'.$filename;
                  echo '<br>';
               */

                /*

                   the semicolon after the Content-type : $basename is important
                   since it was not there.This mail program
                   was not able to see the attachment for the past 1 month
                   --Saravanan 20/04/02

               */

                $subhdr= "--$this->boundary\nContent-Type: $ctype;\n name=\"$basename\";\nContent-Transfer-Encoding: base64\nContent-Disposition: $disposition;\n  filename=\"$basename\"\n";
                //$subhdr= "--$this->boundary\nContent-type: $ctype;\n name=\"$filename\"\nContent-Transfer-Encoding: base64\nContent-Disposition: $disposition;\n  filename=\"$filename\"\n";
                $ata[$k++] = $subhdr;
                // non encoded line length
                $linesz= filesize( $filename)+1;
                $fp= fopen( $filename, 'r' );
                $ata[$k++] = chunk_split(base64_encode(fread( $fp, $linesz)));

                fclose($fp);

        }

        $this->fullBody .= implode($sep, $ata);

        //echo $this->fullBody;
}


} // class Mail



?>