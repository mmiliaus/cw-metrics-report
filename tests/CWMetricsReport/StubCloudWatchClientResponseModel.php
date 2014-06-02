<?php
/**
 * Created by JetBrains PhpStorm.
 * User: mmiliauskass
 * Date: 03/06/2014
 * Time: 00:03
 * To change this template use File | Settings | File Templates.
 */

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
