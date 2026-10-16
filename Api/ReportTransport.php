<?php

/**
 * @package     Supertext Translation for Magento
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

declare(strict_types=1);

namespace Supertext\Translation\Api;

use Supertext\PluginHealth\Http\HttpRequest;
use Supertext\PluginHealth\Http\HttpResponse;
use Supertext\PluginHealth\Http\HttpTransport;
use Supertext\PluginHealth\Http\TransportException;
use Supertext\Sdk\Http\HttpRequest as SdkRequest;
use Supertext\Sdk\Http\HttpTransport as SdkTransport;

/**
 * Sends Supertext Plugin Health's version reports through an SDK transport (the fake one in the
 * tests, so the key check and the reports share it). Both libraries have the same HTTP shape; only
 * the namespaces differ.
 */
final class ReportTransport implements HttpTransport
{
    public function __construct(private readonly SdkTransport $transport)
    {
    }

    public function send(HttpRequest $request): HttpResponse
    {
        try {
            $response = $this->transport->send(new SdkRequest($request->method, $request->url, $request->headers, $request->body, $request->timeout));
        } catch (\Throwable $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }

        return new HttpResponse($response->status, $response->body, $response->headers);
    }
}
