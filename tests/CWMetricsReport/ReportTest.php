<?php

namespace CWMetricsReport;

use Symfony\Component\Yaml\Parser;
use Aws\CloudWatch\CloudWatchClient;

class ReportTest extends \PHPUnit_Framework_TestCase
{
    protected $awsConfig;
    protected $client;
    
    protected function setUp()
    {
        $yaml = new Parser();
        $this->awsConfig = $yaml->parse(file_get_contents(__DIR__.'/../data/aws.yml'))['aws'];
        $this->client = new StubCloudWatchClient();
    }
    
    public function testMetricNameParse()
    {
        $report = new Report($this->client);

        $i = $report->
            addMetric('AWS/EC2::CPUUtilization')->
        getMonGetStatsParamArray()[0];

        $this->assertEquals($i['name'], 'CPUUtilization');
        $this->assertEquals($i['namespace'], 'AWS/EC2');
    }

    public function testDefaultParams()
    {
        $report = new Report($this->client);

        $i = $report->
            addMetric('AWS/EC2::CPUUtilization')->
        getMonGetStatsParamArray()[0];

        $this->assertEquals($i['period'], Report::DEFAULT_PERIOD);
        $this->assertEquals($i['statistics'], array(MetricFilter::AGGREGATE_BY_AVERAGE));
    }

    public function testGlobalParam()
    {
        $report = new Report($this->client);
        $startTime = time() - (3600 * 24);
        $endTime = time();

        $i = $report->
            setStartTime(
                $startTime
            )->
            setEndTime(
                $endTime
            )->
            setPeriod(3600)->
            addDimension('AutoScalingGroupName', 'test-as-group')->
            addMetric('AWS/EC2::CPUUtilization')->
        getMonGetStatsParamArray()[0];

        $this->assertEquals($i['startTime'], $startTime);
        $this->assertEquals($i['endTime'], $endTime);
        $this->assertEquals($i['dimensions'][0], array('Name'=>'AutoScalingGroupName', 'Value'=>'test-as-group'));
    }

    public function testParamOverride()
    {
        $report = new Report($this->client);

        $i = $report->
            setPeriod(3600)->
            addDimension('AutoScalingGroupName', 'test-as-group')->
            addMetric('AWS/EC2::CPUUtilization')->begin()->
                aggregateBy(MetricFilter::AGGREGATE_BY_MINIMUM)->
            end()->
        getMonGetStatsParamArray()[0];

        $this->assertEquals($i['statistics'], array(MetricFilter::AGGREGATE_BY_MINIMUM));
    }

    public function testScopeChange()
    {
        $report = new Report($this->client);

        $params = $report->
            setStartTime(
                time() - (3600 * 24)
            )->
            setEndTime(
                time()
            )->
            setPeriod(3600)->
            addDimension('AutoScalingGroupName', 'test-as-group')->
            addMetric('System/Linux::DiskSpaceUtilization')->begin()->
                addDimension('Scope1_Dim1', 'scope1_dim1_value')->
                addDimension('Scope1_Dim2', 'scope1_dim2_value')->
                aggregateBy(MetricFilter::AGGREGATE_BY_MINIMUM)->
            end()->
            addMetric('System/Linux::DiskSpaceUsed')->begin()->
                addDimension('Scope2_Dim1', 'scope2_dim1_value')->
                addDimension('Scope2_Dim2', 'scope2_dim2_value')->
            end()->
        getMonGetStatsParamArray();

        // two instructions created by the addMetric function calls
        $dsUtilization = $params[0];
        $dsSpaceUsed = $params[1];
        
        $this->assertEquals($dsUtilization['dimensions'], array(
            array(
                'Name' => 'AutoScalingGroupName',
                'Value' => 'test-as-group'
            ),
            array(
                'Name' => 'Scope1_Dim1',
                'Value' => 'scope1_dim1_value'
            ),
            array(
                'Name' => 'Scope1_Dim2',
                'Value' => 'scope1_dim2_value'
            )
        ));
        $this->assertEquals($dsUtilization['statistics'], array(MetricFilter::AGGREGATE_BY_MINIMUM));

        $this->assertEquals($dsSpaceUsed['dimensions'], array(
            array(
                'Name' => 'AutoScalingGroupName',
                'Value' => 'test-as-group'
            ),
            array(
                'Name' => 'Scope2_Dim1',
                'Value' => 'scope2_dim1_value'
            ),
            array(
                'Name' => 'Scope2_Dim2',
                'Value' => 'scope2_dim2_value'
            )
        ));
        $this->assertEquals($dsSpaceUsed['statistics'], array(MetricFilter::AGGREGATE_BY_AVERAGE));
    }

    /**
     * Sanity check of the report
     */
    public function testReport()
    {
        $report = $this->generateReportWithMockedData();

        $dateTime = $report['Date Time'];
        $cpuUtilization = $report['CPUUtilization (Percent)'];
        $diskSpaceUtilization = $report['DiskSpaceUtilization (Percent)'];
        $apachePHPHeartBeat = $report['ApachePHPHeartbeat (Count)'];

        foreach ($dateTime as $t) {
            // every metric column, must have a record for every $dateTime element
            $this->assertTrue(array_key_exists($t, $cpuUtilization));
            $this->assertTrue(array_key_exists($t, $diskSpaceUtilization));
            $this->assertTrue(array_key_exists($t, $apachePHPHeartBeat));

            // every metric column record must be numeric
            $this->assertTrue(is_numeric($cpuUtilization[$t]));
            $this->assertTrue(is_numeric($diskSpaceUtilization[$t]));
            $this->assertTrue(is_numeric($apachePHPHeartBeat[$t]));
        }
    }

    /**
     * A helper method to generate a CloudWatch report without actually using the CloudWatch
     *
     * @return array
     */
    private function generateReportWithMockedData()
    {
        $mockData = json_decode(file_get_contents(__DIR__.'/../data/CloudWatchClientMockData.json'));
        $map = array();
        foreach ($mockData as $key=>$value) {
            $map[] = array(
                unserialize($key),
                new StubCloudWatchClientResponseModel(unserialize($value))
            );
        }

        // it's important not to generate these dynamically when describing the report,
        // because the input to the mocked `getMetricsStatistics` must match identically
        $startTime = $map[0][0]['StartTime'];
        $endTime = $map[0][0]['EndTime'];

        // Create a stub for the SomeClass class.
        $stubCWClient = $this->getMock('CWMetricsReport\StubCloudWatchClient');

        // Configure the stub.
        $stubCWClient->expects($this->any())
            ->method('getMetricStatistics')
            ->will($this->returnValueMap($map));

        $cwReport = new Report($stubCWClient);

        return $cwReport->
            setStartTime(
                $startTime
            )->
            setEndTime(
                $endTime
            )->
            setPeriod(3600)->
            addDimension('AutoScalingGroupName', $this->awsConfig['as_group_name'])->
            addMetric('AWS/EC2::CPUUtilization')->
            addMetric('System/Linux::DiskSpaceUtilization')->begin()->
                addDimension('Filesystem', '/dev/xvda1')->
                addDimension('MountPath', '/')->
            end()->
            addMetric('System/LAMP::ApachePHPHeartbeat')->begin()->
                aggregateBy('Minimum')->
            end()->
        getStatsArray();
    }
}