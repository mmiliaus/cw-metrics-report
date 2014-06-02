# CWMetricsReport

Helps generating AWS CloudWatch reports by providing a beautiful DSL for it.

## Usage

```php

use CWMetricsReport\Report;

// initialize a CloudWatchClient
$client = CloudWatchClient::factory(array(
    'key'    => $awsConfig['key'],
    'secret' => $awsConfig['secret'],
    'region' => $awsConfig['region']
));

// create a report instance
$report = new Report($client);

// describe your report preferences
$stats = $report->

    // global parameters
    setStartTime(
        time() - (3600 * 24)
    )->
    setEndTime(
        time()
    )->
    setPeriod(3600)->
    addDimension('AutoScalingGroupName', $awsConfig['as_group_name'])->

    // attach some metrics (`Namespace::MetricName`)
    addMetric('AWS/EC2::CPUUtilization')->
    addMetric('System/Linux::DiskSpaceUtilization')->begin()->
        // add custom parameters for a specific metric
        addDimension('Filesystem', '/dev/xvda1')->
        addDimension('MountPath', '/')->
    end()->
    addMetric('System/LAMP::ApachePHPHeartbeat')->begin()->
        aggregateBy('Minimum')->
    end()->

getMonGetStatsParamArray();
```

## Example output

```
["Date Time"]=>
  array(24) {
    [0]=>
    string(20) "2014-06-01T14:00:00Z"
    [1]=>
    string(20) "2014-06-01T15:00:00Z"

    ...

    [23]=>
    string(20) "2014-06-02T13:00:00Z"
  }
  ["CPUUtilization (Percent)"]=>
  array(24) {
    ["2014-06-01T14:00:00Z"]=>
    string(17) "4.917333333333334"
    ["2014-06-01T15:00:00Z"]=>
    string(5) "5.026"

    ...

    ["2014-06-02T13:00:00Z"]=>
    string(6) "5.5935"
  }
  ["DiskSpaceUtilization (Percent)"]=>
  array(24) {
    ["2014-06-01T14:00:00Z"]=>
    string(18) "21.944473779993714"
    ["2014-06-01T15:00:00Z"]=>
    string(17) "21.95595299573014"

    ...

    ["2014-06-02T13:00:00Z"]=>
    string(18) "22.164718490148264"
  }
  ["ApachePHPHeartbeat (Count)"]=>
  array(24) {
    ["2014-06-01T14:00:00Z"]=>
    string(3) "1.0"
    ["2014-06-01T15:00:00Z"]=>
    string(3) "1.0"

    ...

    ["2014-06-02T13:00:00Z"]=>
    string(3) "1.0"
  }
}
```

## License
Code in this repository is licensed under the [WTFPL](http://en.wikipedia.org/wiki/WTFPL).
