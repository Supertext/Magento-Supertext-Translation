<?php

/**
 * @package     Supertext Translation for Magento
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

declare(strict_types=1);

namespace Supertext\Translation\Controller\Ping;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Supertext\Translation\Model\Reporting;

/**
 * GET /supertext/ping?challenge=… — the public ping of the Supertext Commando Center: no login,
 * no form key, never cached (Cache-Control: no-store, so neither the full-page cache nor Varnish
 * keeps it). Answers exactly what Supertext Plugin Health's InstallationReporter::pingResponse() returns: 200 with
 * the challenge, 400 for a bad challenge, 404 while version reports are off.
 */
class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RawFactory $rawFactory,
        private readonly Reporting $reporting,
    ) {
    }

    public function execute(): Raw
    {
        $challenge = $this->request->getParam('challenge');
        $ping      = $this->reporting->ping(\is_string($challenge) ? $challenge : null);
        $result    = $this->rawFactory->create();
        $result->setHttpResponseCode($ping->status);

        foreach ($ping->headers as $name => $value) {
            $result->setHeader($name, $value, true);
        }

        $result->setHeader('Pragma', 'no-cache', true);

        return $result->setContents($ping->body);
    }
}
