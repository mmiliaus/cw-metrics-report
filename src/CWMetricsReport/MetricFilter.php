<?php
/**
 * Created by JetBrains PhpStorm.
 * User: mmiliauskass
 * Date: 02/06/2014
 * Time: 11:43
 * To change this template use File | Settings | File Templates.
 */

namespace CWMetricsReport;

class MetricFilter
{
    const AGGREGATE_BY_AVERAGE = 'Average';
    const AGGREGATE_BY_SUM = 'Sum';
    const AGGREGATE_BY_SAMPLE_COUNT = 'SampleCount';
    const AGGREGATE_BY_MINIMUM = 'Minimum';
    const AGGREGATE_BY_MAXIMUM = 'Maximum';

    /**
     * @var MetricName
     */
    private $name;

    /**
     * @var --namespace
     */
    private $namespace;

    /**
     * @var array --dimensions
     */
    private $dimensions = array();

    /**
     * @var string --statistics
     */
    private $aggregateBy = self::AGGREGATE_BY_AVERAGE;

    /**
     * @var --start-time
     */
    private $startTime;

    /**
     * @var --end-time
     */
    private $endTime;

    /**
     * @var --period
     */
    private $period = null;


    /**
     * @param $namespaceWithName e.g.: System/Linux:MemoryUtilization
     */
    public function __construct($namespaceWithName)
    {
        $namespaceWithNameArray = explode("::", $namespaceWithName);
        $this->namespace = $namespaceWithNameArray[0];
        $this->name = $namespaceWithNameArray[1];
    }

    public function addDimension($key, $value)
    {
        $this->dimensions[$key] = $value;
    }

    public function setStartTime($timestamp)
    {
        $this->startTime = $timestamp;
    }

    public function setEndTime($timestamp)
    {
        $this->endTime = $timestamp;
    }

    public function setAggregateBy($functionName)
    {
        $this->aggregateBy = $functionName;
    }

    public function setPeriod($periodInSeconds)
    {
        $this->period = $periodInSeconds;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getNamespace()
    {
        return $this->namespace;
    }

    public function getAggregateBy()
    {
        return $this->aggregateBy;
    }

    public function getDimensions()
    {
        return $this->dimensions;
    }

    public function getStartTime()
    {
        return $this->startTime;
    }

    public function getEndTime()
    {
        return $this->endTime;
    }

    public function getPeriod()
    {
        return $this->period;
    }
}