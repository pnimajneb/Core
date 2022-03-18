<?php
namespace exface\Core\DataConnectors;

use exface\Core\CommonLogic\AbstractDataConnectorWithoutTransactions;
use exface\Core\Interfaces\DataSources\DataQueryInterface;
use exface\Core\Exceptions\DataSources\DataConnectionQueryTypeError;
use exface\Core\Interfaces\Communication\CommunicationMessageInterface;
use exface\Core\Interfaces\Communication\CommunicationReceiptInterface;
use exface\Core\Interfaces\Communication\CommunicationConnectionInterface;
use exface\Core\CommonLogic\Communication\CommunicationReceipt;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mime\Email;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Communication\RecipientGroupInterface;
use exface\Core\Interfaces\Communication\EmailRecipientInterface;
use exface\Core\Communication\Messages\EmailMessage;
use exface\Core\Exceptions\Communication\CommunicationNotSentError;
use exface\Core\DataTypes\EmailDataType;
use exface\Core\Exceptions\DataTypes\DataTypeCastingError;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Communication\Messages\TextMessage;
use exface\Core\DataTypes\HtmlDataType;

/**
 * Sends emails via SMTP
 * 
 * This connector uses the popular [Symfony mailer component](https://symfony.com/doc/current/mailer.html).
 * 
 * This connector is typically used within a communication channel. In the simplest case,
 * you will need to set up an SMTP connection and assign it to the default email channel
 * `exface.Core.EMAIL` - any messages sent through this channel will then be directed to
 * the configured SMTP server. 
 * 
 * You can also set up your own communicatio channel to define a default message structure, etc.
 * 
 * ## Examples
 * 
 * ### SMTP Server without authentication
 * 
 * ```
 *  {
 *      "host": "SMTP.yourdomain.com",
 *      "port": 25,
 *      "tls": true,
 *      "user": "andrej_ka@gmx.de",
 *      "password": "m3$5p5D4gx.3"
 *  }
 *  
 * ```
 * 
 * ### SMTP Server with TLS authentication
 * 
 * ```
 *  {
 *      "host": "SMTP.yourdomain.com",
 *      "port": 465,
 *      "tls": true,
 *      "user": "username",
 *      "password": "password"
 *  }
 *  
 * ```
 * 
 * @author Andrej Kabachnik
 *
 */
class SmtpConnector extends AbstractDataConnectorWithoutTransactions implements CommunicationConnectionInterface
{
    private $dsn = null;
    
    private $scheme = null;
    
    private $host = null;
    
    private $port = 25;
    
    private $user = null;
    
    private $password = null;
    
    private $options = [];
    
    private $tls = false;
    
    private $from = null;
    
    private $headers = [];
    
    private $suppressAutoResponse = true;
    
    private $footer = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractDataConnector::performConnect()
     */
    protected function performConnect()
    {
        return;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractDataConnector::performQuery()
     */
    protected function performQuery(DataQueryInterface $query)
    {
        if (! $query instanceof SymfonyNotifierMessageDataQuery) {
            throw new DataConnectionQueryTypeError($this, 'Invalid query type for connector "' . $this->getAliasWithNamespace() . '": expecting "SymfonyNotifierMessageDataQuery", received "' . get_class($query) . '"!');
        }
        $this->getTransport()->send($query->getMessage());
        return $query;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractDataConnector::performDisconnect()
     */
    protected function performDisconnect()
    {
        return;
    }
    
    /**
     * 
     * @return Dsn
     */
    protected function getDsn() : Dsn
    {
        if ($this->dsn !== null) {
            return Dsn::fromString($this->dsn);
        }
        return new Dsn($this->getScheme(), $this->getHost(), $this->getUser(), $this->getPassword(), $this->getPort(), $this->getOptions());
    }
    
    /**
     * Custom DSN as used in Symfony mailer and Swift mailer libraries
     * 
     * @uxon-property dsn
     * @uxon-type uri
     * @uxon-template smtps://smtp.example.com?
     * 
     * @param string $value
     * @return SmtpConnector
     */
    protected function setDsn(string $value) : SmtpConnector
    {
        $this->dsn = $value;
        return $this;
    }
    
    /**
     * 
     * @return TransportInterface
     */
    protected function getTransport() : TransportInterface
    {
        // $transport = new EsmtpTransport($this->getHost(), $this->getPort(), $this->getTls());
        $transport = (new EsmtpTransportFactory())->create($this->getDsn());
        return $transport;
    }
    
    /**
     * 
     * @return MailerInterface
     */
    protected function getMailer() : MailerInterface
    {
        $mailer = new Mailer($this->getTransport());
        return $mailer;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\CommunicationConnectionInterface::communicate()
     */
    public function communicate(CommunicationMessageInterface $message) : CommunicationReceiptInterface
    {
        if (! ($message instanceof TextMessage)) {
            throw new CommunicationNotSentError($message, 'Cannot send email: invalid message type!');
        }
        $mailer = $this->getMailer();
        
        $email = (new Email());
        
        // Headers
        foreach ($this->getMessageHeaders() as $header => $value) {
            $email->getHeaders()->addTextHeader($header, $value);
        }
        
        // Addresses
        if ($from = $this->getFrom()) {
            $email->from($from);
        }
        foreach ($this->getEmails($message->getRecipients()) as $address) {
            $email->addTo($address);
        }
        
        // Email specific stuff
        if ($message instanceof EmailMessage) {
            //->cc('cc@example.com')
            //->bcc('bcc@example.com')
            //->replyTo('fabien@example.com')
            
            // Priority
            if ($priority = $message->getPriority()) {
                $email->priority($priority);
            }
            
            $email->subject($message->getSubject() ?? '');
        }
        
        $body = $message->getText();
        $footer = $this->getFooter();
        if (HtmlDataType::isValueHtml($body)) {
            $email->html($body . ($footer !== null ? '<footer>' . $footer . '</footer>': ''));
        } else {
            $email->text($body . ($footer !== null ? "\n\n" . $footer : ''));
        }
        
        $mailer->send($email);
        
        return new CommunicationReceipt($message, $this);
    }
    
    /**
     * 
     * @param array $recipients
     * @return array
     */
    protected function getEmails(array $recipients) : array
    {
        $emails = [];
        foreach ($recipients as $recipient) {
            switch (true) {
                case $recipient instanceof RecipientGroupInterface:
                    $emails = array_merge($emails, $this->getEmails($recipient->getRecipients()));
                    break;
                case $recipient instanceof EmailRecipientInterface:
                    if ($email = $recipient->getEmail()) {
                        $emails[] = $email;
                    }
                    break;
                default:
                    // TODO
            }
        }
        return $emails;
    }
    
    /**
     * 
     * @return string
     */
    protected function getScheme() : string
    {
        return $this->scheme ?? ($this->getTls() ? 'smpts://' : 'smpt://');
    }
    
    /**
     * 
     * @param string $value
     * @return SmtpConnector
     */
    protected function setScheme(string $value) : SmtpConnector
    {
        $this->scheme = $value;
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    protected function getHost() : string
    {
        return $this->host;
    }

    
   /**
    * The host name or IP address of the SMTP server
    * 
    * @uxon-property host
    * @uxon-type uri
    * 
    * @param string $value
    * @return SmtpConnector
    */
    protected function setHost(string $value) : SmtpConnector
    {
        $this->host = $value;
        return $this;
    }
   
    /**
     * 
     * @return int
     */
    protected function getPort() : int
    {
        return $this->port;
    }
    
    /**
     * SMTP server port - typically 25, 465 or 587
     *
     * @uxon-property port
     * @uxon-type uri
     * @uxon-default 25
     *
     * @return string
     */
    protected function setPort(int $value) : SmtpConnector
    {
        $this->port = $value;
        return $this;
    }
    
    /**
     * 
     * @return string|NULL
     */
    protected function getUser() : ?string
    {
        return $this->user;
    }
    
    /**
     * Username for SMTP server authentication
     * 
     * @uxon-property user
     * @uxon-type string
     * 
     * @param string $value
     * @return SmtpConnector
     */
    protected function setUser(string $value) : SmtpConnector
    {
        $this->user = $value;
        return $this;
    }
    
    /**
     * 
     * @return string|NULL
     */
    protected function getPassword() : ?string
    {
        return $this->password;
    }
    
    /**
     * Password for SMTP server authentication
     * 
     * @uxon-property password
     * @uxon-type password
     * 
     * @param string $value
     * @return SmtpConnector
     */
    protected function setPassword(string $value) : SmtpConnector
    {
        $this->password = $value;
        return $this;
    }
    
    protected function getTls() : bool
    {
        return $this->tls;
    }
    
    /**
     * Set to TRUE to use TLS for secure SMTP
     *
     * @uxon-property tls
     * @uxon-type boolean
     * @uxon-default false
     *
     * @return string
     */
    protected function setTls(bool $value) : SmtpConnector
    {
        $this->tls = $value;
        return $this;
    }
    
    /**
     * 
     * @return array
     */
    protected function getOptions() : array
    {
        return $this->options;
    }
    
    /**
     *
     * @return string
     */
    protected function getFrom() : ?string
    {
        return $this->from;
    }
    
    /**
     * From email address
     *
     * @uxon-property from
     * @uxon-type string
     * @uxon-required true
     *
     * @param string $value
     * @return EmailMessage
     */
    protected function setFrom(string $value) : SmtpConnector
    {
        try {
            $email = EmailDataType::cast($value);
        } catch (DataTypeCastingError $e) {
            throw new InvalidArgumentException('Invalid from-address for email message: "' . $email . '"!');
        }
        $this->from = $email;
        return $this;
    }
    
    /**
     *
     * @return string[]
     */
    protected function getMessageHeaders() : array
    {
        $headers = $this->headers;
        if ($this->getSuppressAutoResponse() === true) {
            $headers['X-Auto-Response-Suppress'] = 'OOF, DR, RN, NRN, AutoReply';
        }
        return $headers;
    }
    
    /**
     * Custom message headers for all messages
     *
     * @uxon-property message_headers
     * @uxon-type object
     * @uxon-template {"X-Auto-Response-Suppress": "OOF, DR, RN, NRN, AutoReply"}
     *
     * @param string[]|UxonObject $value
     * @return EmailMessage
     */
    protected function setMessageHeaders($value) : SmtpConnector
    {
        if ($value instanceof UxonObject) {
            $array  = $value->toArray();
        } elseif (is_array($value)) {
            $array = $value;
        } else {
            throw new InvalidArgumentException('Invalid email message headers: "' . $value . '" - expecting array or UXON!');
        }
        $this->headers = $array;
        return $this;
    }
    
    /**
     * 
     * @return Bool
     */
    protected function getSuppressAutoResponse() : Bool
    {
        return $this->suppressAutoResponse;
    }
    
    /**
     * Set to TRUE to tell auto-repliers ("email holiday mode") to not reply to this message because it's an automated email
     *
     * @uxon-property suppress_auto_response
     * @uxon-type boolean
     * @uxon-default true
     *
     * @param bool $value
     * @return EmailMessage
     */
    protected function setSuppressAutoResponse(bool $value) : SmtpConnector
    {
        $this->suppressAutoResponse = $value;
        return $this;
    }
    
    /**
     * 
     * @return string|NULL
     */
    public function getFooter() : ?string
    {
        return $this->footer;
    }
    
    /**
     * A footer to be appended to all messages sent through this connection (plain text or HTML)
     * 
     * @uxon-property footer
     * @uxon-type string
     * 
     * @param string $value
     * @return SmtpConnector
     */
    public function setFooter(string $value) : SmtpConnector
    {
        $this->footer = $value;
        return $this;
    }
}