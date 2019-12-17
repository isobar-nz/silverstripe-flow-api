<?php

namespace Isobar\Flow\Handler;

use Isobar\Flow\Exception\FlowException;
use Monolog\Handler\AbstractHandler;
use Monolog\Logger;
use Psr\Log\InvalidArgumentException;
use SilverStripe\Control\Email\Email;
use SilverStripe\Core\Convert;

class FlowErrorHandler extends AbstractHandler
{
    /**
     * The email addresses to which the message will be sent
     * @var array
     */
    protected $to;

    /**
     * The email address from which the message will be sent
     * @var string
     */
    protected $from;

    /**
     * Optional BCC email address
     * @var string
     */
    protected $bcc;

    /**
     * The subject of the email
     * @var string
     */
    protected $subject;

    /**
     * Optional headers for the message
     * @var array
     */
    protected $headers = [];

    /**
     * Optional parameters for the message
     * @var array
     */
    protected $parameters = [];

    /**
     * The wordwrap length for the message
     * @var int
     */
    protected $maxColumnWidth;

    /**
     * @param string|array $to The receiver of the mail
     * @param string $subject The subject of the mail
     * @param string $from The sender of the mail
     * @param int $level The minimum logging level at which this handler will be triggered
     * @param null $bcc
     * @param bool $bubble Whether the messages that are handled can bubble up the stack or not
     * @param int $maxColumnWidth The maximum column width that the message lines will have
     */
    public function __construct($to, $subject, $from, $level = Logger::ERROR, $bcc = null, $bubble = true, $maxColumnWidth = 70)
    {
        parent::__construct($level, $bubble);
        $this->to = is_array($to) ? $to : [$to];
        $this->subject = $subject;
        $this->from = $from;
        $this->bcc = $bcc;
        $this->maxColumnWidth = $maxColumnWidth;
    }

    /**
     * Handles a record.
     *
     * All records may be passed to this method, and the handler should discard
     * those that it does not want to handle.
     *
     * The return value of this function controls the bubbling process of the handler stack.
     * Unless the bubbling is interrupted (by returning true), the Logger class will keep on
     * calling further handlers in the stack with a given log record.
     *
     * @param array $record The record to handle
     * @return bool true means that this handler handled the record, and that bubbling is not permitted.
     *                        false means the record was either not processed or that this handler allows bubbling.
     */
    public function handle(array $record)
    {
        // Send an email

        // Context
        if (isset($record['context'])) {
            if (isset($record['context']['exception'])) {
                $exception = $record['context']['exception'];

                if ($exception instanceof FlowException) {
                    // Build up a semblance of a message
                    $body = '------------' . "\n";
                    $body .= 'An error was detected on your website:' . "\n";
                    $body .= '------------' . "\n\n";
                    $body .= 'Code: ' . $exception->getCode() . "\n";
                    $body .= 'Message: ' . $exception->getMessage();

                    $this->send($body);
                }
            }
        }

        return false;
    }


    /**
     * Add headers to the message
     *
     * @param string|array $headers Custom added headers
     * @return self
     */
    public function addHeader($headers)
    {
        foreach ((array)$headers as $header) {
            if (strpos($header, "\n") !== false || strpos($header, "\r") !== false) {
                throw new InvalidArgumentException('Headers can not contain newline characters for security reasons');
            }
            $this->headers[] = $header;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function send($content)
    {
        $content = wordwrap(Convert::raw2xml($content), $this->maxColumnWidth);

        /** @var Email $email */
        $email = Email::create($this->from);
        $email->setSubject($this->subject);
        $email->setBody($content);

        if ($this->bcc) {
            $email->addBCC($this->bcc);
        }

        foreach ($this->to as $to) {
            $email->setTo($to);
            $email->sendPlain();
        }
    }

}
