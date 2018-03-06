<?php
/**
 * @file DrupalCheckEffectiveUrlMiddleware.php
 */

namespace mikebell\drupalcheck;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class DrupalCheckEffectiveUrlMiddleware
{

  /**
   * @var Callable
   */
    protected $nextHandler;
    /**
     * @var string
     */
    protected $headerName;

    protected static $effective_url = [];

    protected static $effective_url_status = [];

    /**
     * @param callable $nextHandler
     * @param string   $headerName  The header name to use for storing effective url
     */
    public function __construct(callable $nextHandler, $headerName = 'X-GUZZLE-EFFECTIVE-URL')
    {
        $this->nextHandler = $nextHandler;
        $this->headerName = $headerName;
    }

    /**
     * Inject effective-url and status code header into response.
     *
     * @param RequestInterface $request
     * @param array            $options
     *
     * @return RequestInterface
     */
    public function __invoke(RequestInterface $request, array $options)
    {
        $fn = $this->nextHandler;
        return $fn($request, $options)->then(function (ResponseInterface $response) use ($request, $options) {
            // Get URL and status code of each redirect.
            self::$effective_url[] = $request->getUri()->__toString();
            self::$effective_url_status[] = $response->getStatusCode();
            // Add redirect URL and status code to response headers.
            $response = $response->withAddedHeader($this->headerName, self::$effective_url);
            return $response->withAddedHeader($this->headerName . '-Status', self::$effective_url_status);
        });
    }



    /**
     * Prepare a middleware closure to be used with HandlerStack
     *
     * @param string $headerName The header name to use for storing effective url
     *
     * @return \Closure
     */
    public static function middleware($headerName = 'X-Guzzle-Effective-Url')
    {
        return function (callable $handler) use (&$headerName) {
            return new static($handler, $headerName);
        };
    }
}
