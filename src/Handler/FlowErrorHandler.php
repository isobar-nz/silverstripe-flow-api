<?php

namespace Isobar\Flow\Handler;

use Isobar\Flow\Exception\FlowException;
use Monolog\Handler\AbstractHandler;
use Monolog\Logger;
use Psr\Log\InvalidArgumentException;

class FlowErrorHandler extends AbstractHandler
{
    /**
     * The email addresses to which the message will be sent
     * @var array
     */
    protected $to;

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
     * The Content-type for the message
     * @var string
     */
    protected $contentType = 'text/plain';

    /**
     * The encoding for the message
     * @var string
     */
    protected $encoding = 'utf-8';

    /**
     * @param string|array $to The receiver of the mail
     * @param string $subject The subject of the mail
     * @param string $from The sender of the mail
     * @param int $level The minimum logging level at which this handler will be triggered
     * @param bool $bubble Whether the messages that are handled can bubble up the stack or not
     * @param int $maxColumnWidth The maximum column width that the message lines will have
     */
    public function __construct($to, $subject, $from, $level = Logger::ERROR, $bubble = true, $maxColumnWidth = 70)
    {
        parent::__construct($level, $bubble);
        $this->to = is_array($to) ? $to : [$to];
        $this->subject = $subject;
        $this->addHeader(sprintf('From: %s', $from));
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
        // TODO: Implement handle() method.
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
     * @param  string|array $headers Custom added headers
     * @return self
     */
    public function addHeader($headers)
    {
        foreach ((array) $headers as $header) {
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
        $content = wordwrap($content, $this->maxColumnWidth);
        $headers = ltrim(implode("\r\n", $this->headers) . "\r\n", "\r\n");
        $headers .= 'Content-type: ' . $this->getContentType() . '; charset=' . $this->getEncoding() . "\r\n";
        if ($this->getContentType() == 'text/html' && false === strpos($headers, 'MIME-Version:')) {
            $headers .= 'MIME-Version: 1.0' . "\r\n";
        }

        $subject = $this->subject;

        $parameters = implode(' ', $this->parameters);
        foreach ($this->to as $to) {
            mail($to, $subject, $content, $headers, $parameters);
        }
    }

    /**
     * @return string $contentType
     */
    public function getContentType()
    {
        return $this->contentType;
    }

    /**
     * @return string $encoding
     */
    public function getEncoding()
    {
        return $this->encoding;
    }
}
