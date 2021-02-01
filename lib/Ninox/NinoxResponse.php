<?php


namespace Ninox;

/**
 * Class NinoxResponse
 * @package Ninox
 * @property int headerSize
 * @property int statusCode
 * @property \stdClass|mixed|string|null responseBody
 * @property array responseHeaders
 */
class NinoxResponse extends \stdClass
{
    const HTTP_RESPONSE_CODE_TOO_MANY = 429;

    /**
     * @var int
     */
    public $headerSize;
    /**
     * @var int
     */
    public $statusCode;
    /**
     * @var mixed|\stdClass|string
     */
    public $responseBody;
    /**
     * @var array
     */
    public $responseHeaders;

    /**
     * @param int|null $expectedStatusCode
     * @return bool
     */
    public function isOK(?int $expectedStatusCode = 200): bool
    {
        return $this->statusCode === $expectedStatusCode;
    }

    /**
     * @return bool
     */
    public function needsThrottle(): bool
    {
        return $this->statusCode === self::HTTP_RESPONSE_CODE_TOO_MANY;
    }


}