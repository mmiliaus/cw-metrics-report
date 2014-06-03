<?php

namespace CWMetricsReport;

/**
 * Guzzle\Service\Resource\Model stub class.
 *
 * Class StubCloudWatchClientResponseModel
 * @package CWMetricsReport
 */
class StubCloudWatchClientResponseModel
{
    private $responseArray;

    public function __construct($responseArray)
    {
        $this->responseArray = $responseArray;
    }

    public function toArray()
    {
        return $this->responseArray;
    }
}
