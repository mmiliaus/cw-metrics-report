<?php
/**
 * Created by JetBrains PhpStorm.
 * User: mmiliauskass
 * Date: 02/06/2014
 * Time: 11:44
 * To change this template use File | Settings | File Templates.
 */

namespace CWMetricsReport;


/**
 * A DSL for generating AWS CloudWatch reports
 *
 * Usage example:
    $cwReport = new CWMetricsReport();

    return $cwReport->
        setStartTime(
            $startDate->format('Y-m-d\TH:i:00\Z')
        )->
        setEndTime(
            $today->format('Y-m-d\TH:i:00\Z')
        )->
        setPeriod(3600)->
        addDimension('AutoScalingGroupName', $this->taskOptions['as-group-name'])->
        addMetric('System/Linux::MemoryUtilization')->
        addMetric('System/Linux::MemoryUsed')->
        addMetric('System/Linux::DiskSpaceUtilization')->begin()->
            addDimension('Filesystem', '/dev/xvda1')->
            addDimension('MountPath', '/')->
        end()->
        addMetric('System/LAMP::ApachePHPHeartbeat')->begin()->
            aggregateBy('Minimum')->
        end()->
    getStatsArray();
 *
 */
class Report
{
    const AWS_CW_TIMESTAMP_FORMAT = 'Y-m-d\TH:i:00\Z';
    const DEFAULT_PERIOD = 3600;

    /**
     * In order for `begin()` and `end()` to work, `addMetric()` creates and pushes new MetricFilter object to
     * `$instructions` stack. If `begin()` method is called, the last object in the `$instructions` stack is set to
     * `$this->scope`, and every subsequent method is sent to this object, i.e.: addDimensions(), addAggregateBy()
     * and etc... By calling `end()` the `$scope` is set to `null`, and every subsequent calls are sent to `$this`.
     *
     * @var array
     */
    private $instructions = array();

    private $scope = null;


    /**
     * In order to avoid the hassle of setting redundant parameters for every MetricFilter object added by `addMetric()`,
     * certain parameters can be specified on a global scale:
     * - dimensions - if `dimensions` are specified on MetricFilter level, then they are both combined on
     * `mon-get-stats` call
     * - startTime
     * - endTime
     * - period
     */

    // global (key, value) pairs for --dimensions parameter
    private $dimensions = array();

    // global --start-time parameter
    private $startTime;

    // global --end-time parameter
    private $endTime;

    // global --period parameter
    private $period = self::DEFAULT_PERIOD;

    // CloudWatch client
    private $client;


    public function __construct($client)
    {
        $this->client = $client;
    }


    /**
     * `mon-get-stats` --dimensions
     *
     * @param $key
     * @param $value
     * @return $this
     */
    public function addDimension($key, $value)
    {
        if ($this->scope) {
            $this->scope->addDimension($key, $value);
        } else {
            $this->dimensions[$key] = $value;
        }

        return $this;
    }


    /**
     * `mon-get-stats --start-time
     *
     * @param $timestamp
     * @return $this
     */
    public function setStartTime($timestamp)
    {
        if ($this->scope) {
            $this->scope->addStartTime($timestamp);
        } else {
            $this->startTime = $timestamp;
        }

        return $this;
    }

    /**
     * `mon-get-stats --end-time
     *
     * @param $timestamp
     * @return $this
     */
    public function setEndTime($timestamp)
    {
        if ($this->scope) {
            $this->scope->addEndTime($timestamp);
        } else {
            $this->endTime = $timestamp;
        }

        return $this;
    }

    /**
     * `mon-get-stats --period
     *
     * @param $periodInSeconds
     * @return $this
     */
    public function setPeriod($periodInSeconds)
    {
        $this->period = $periodInSeconds;
        return $this;
    }

    /**
     * `mon-get-stats --period
     *
     * @return int
     */
    public function getPeriod()
    {
        return $this->period;
    }


    /**
     * Add new MetricFilter to `$this->instructions` stack.
     *
     * @param $metricNamespaceWithName e.g: "System/Linux::MemoryUtilization"
     * @return $this
     */
    public function addMetric($metricNamespaceWithName)
    {
        $this->instructions[] = new MetricFilter($metricNamespaceWithName);
        return $this;
    }

    /**
     * Usage: `addMetric('Namespace::MetricName')->begin()`
     *
     * Pushes the last object in `$this->instructions` set into the current `$this->scope`.
     *
     * This way every subsequent call to set a specific metric query parameters will be sent to the "current metric", i.e.:
     *
     * addMetric('Namespace::MetricName')->begin()->
     *      setAggregateBy('Minimum')->
     *      addDimension('DimensionKey', 'DimensionValue')->
     * end()
     *
     * @return $this
     */
    public function begin()
    {
        if (!empty($this->instructions)) {
            $this->scope = $this->instructions[count($this->instructions) - 1];
        }
        return $this;
    }

    /**
     * Nullifies the current `$this->scope`. Thus subsequent calls to modify the `mon-get-stats` parameters will be set on a
     * global level:
     *
     * addMetric('Namespace::MetricName')->begin()->
     *      // set parameters for `addMetric(..)` object
     *      setAggregateBy('Minimum')->
     *      addDimension('DimensionKey', 'DimensionValue')->
     * end()
     * // set parameters to `self` (`$this`)
     * ->addStartDate('Timestamp')
     * ->addDimension('DimensionKey', 'DimensionValue')
     *
     * @return $this
     */
    public function end()
    {
        $this->scope = null;
        return $this;
    }

    /**
     * Set the `aggregateBy` property (on metric being added by `addMetric()`) which corresponds to the
     * `mon-get-stats --statistics [Average, Minimum, Maximum...]`
     *
     * @param $functionName
     * @return $this
     */
    public function aggregateBy($functionName)
    {
        if ($this->scope) {
            $this->scope->setAggregateBy($functionName);
        }
        return $this;
    }


    /**
     * Run a set of `mon-get-stats` commands using parameters obtained from the MetricFilter objects in
     * `$this->instructions`, and return an array with data.
     *
     * @return array
     */
    public function getStatsArray()
    {
        $monGetStatsParamArray = $this->getMonGetStatsParamArray();

        // get row of timestamps, for `Date Time` column in the CSV output
        $timestamps = $this->getRowOfTimestamps($this->startTime, $this->endTime);
        $stats = array('Date Time' => $timestamps);

        $acc = array();

        foreach ($monGetStatsParamArray as $param) {
            $cwParams = array(
                'Namespace' => $param['namespace'],
                'MetricName' => $param['name'],
                'Dimensions' => $param['dimensions'],
                'EndTime' => $param['endTime'],
                'StartTime' => $param['startTime'],
                'Period' => $param['period'],
                'Statistics' => $param['statistics']
            );

            // get stats from CloudWatch
            $results = $this->client->getMetricStatistics($cwParams)->toArray();
            $acc[serialize($cwParams)] = serialize($results);


            // generate a "bucket", with timestamps as keys, in order to accurately distribute data recevied from the CloudWatch.
            $statsBucket = $this->getStatsBucket($timestamps);

            // sorting Datapoints by 'Timestamp', because data comes unsorted.
            foreach ($results['Datapoints'] as $dp) {
                $statsBucket[$dp['Timestamp']] = $dp[$param['statistics'][0]];
            }

            // format column name for the CSV feed
            $unit = $results['Datapoints'][0]['Unit'];
            $columnName = $results['Label'] . ' (' . $unit . ')';
            $stats[$columnName] = array();

            // add record to statistics accumulator
            $stats[$columnName] = $statsBucket;
        }

        return $stats;
    }


    private function getRowOfTimestamps($fromTime, $toTime)
    {
        $acc = array();
        $interval = ($toTime - $fromTime) / $this->period;
        for ($i = 0; $i < $interval; $i++) {
            $acc[] = date(self::AWS_CW_TIMESTAMP_FORMAT, $fromTime + ($i * $this->period));
        }
        return $acc;
    }

    /**
     * [ 'timestmap(1)' => '', 'timestamp(2)' => '', ... ]
     *
     * @param $timestampsRow
     * @return array
     */
    private function getStatsBucket($timestampsRow)
    {
        $acc = array();
        foreach ($timestampsRow as $timestamp) {
            $acc[$timestamp] = '';
        }
        return $acc;
    }

    /**
     * Turns a list of the `$this->instructions` for the `mon-get-stats` binary into an array of parameters for the
     * `mon-get-stats`.
     *
     * @return array
     */
    public function getMonGetStatsParamArray()
    {
        $cmdArray = array();

        foreach ($this->instructions as $instruction) {
            // necessary parameters for the `mon-get-stats` query
            $startTime = $instruction->getStartTime() ? $instruction->getStartTime : $this->startTime;
            $endTime = $instruction->getEndTime() ? $instruction->getEndTime : $this->endTime;

            $period = $instruction->getPeriod() ? $instruction->getPeriod() : $this->period;

            $metricName = $instruction->getName();
            $namespace = $instruction->getNamespace();
            $statistics = array($instruction->getAggregateBy());


            // combine global dimensions with instruction(metric) specific dimensions
            $dimensions = array_merge($this->dimensions, $instruction->getDimensions());

            // turn [ key => value, key => value ] hash into "hey=value,key=value" string
            $dimensions =
                array_map(
                    function ($e) {
                        return array('Name' => $e[0], 'Value' => $e[1]);
                    },
                    array_map(null, array_keys($dimensions), array_values($dimensions))
                );

            $cmdArray[] = array(
                'name' => $metricName,
                'namespace' => $namespace,
                'startTime' => $startTime,
                'endTime' => $endTime,
                'period' => $period,
                'statistics' => $statistics,
                'dimensions' => $dimensions
            );
        }
        return $cmdArray;
    }
}