<?php

namespace WayToHealth\OpenMetrics\Ummon;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use OpenMetricsPhp\Exposition\Text\Collections\CounterCollection;
use OpenMetricsPhp\Exposition\Text\Collections\GaugeCollection;
use OpenMetricsPhp\Exposition\Text\Collections\LabelCollection;
use OpenMetricsPhp\Exposition\Text\HttpResponse;
use OpenMetricsPhp\Exposition\Text\Metrics\Counter;
use OpenMetricsPhp\Exposition\Text\Metrics\Gauge;
use OpenMetricsPhp\Exposition\Text\Types\Label;
use OpenMetricsPhp\Exposition\Text\Types\MetricName;
use Psr\Http\Message\ResponseInterface;
use stdClass;

/**
 * Class UmmonExporter
 * @package WayToHealth\OpenMetrics\Ummon
 */
class UmmonExporter
{

    /**
     * @var Client
     */
    protected $httpClient;
    /**
     * @var string
     */
    private $host;

    /**
     * UmmonExporter constructor.
     *
     * @param string $host
     * @param string $user
     * @param string $password
     * @param string $scheme
     */
    public function __construct(string $host, string $user, string $password, string $scheme)
    {
        $this->host = $host;
        $this->httpClient = new Client([
            'base_uri' => $scheme . '://' . $host,
            'auth'     => [$user, $password],
        ]);
    }

    public function run(): void
    {
        try {
            $statusData = $this->getStatusData();
            $taskData = $this->getTaskData();
            $instanceData = $this->getInstanceData();

            HttpResponse::fromMetricCollections(
                ...$this->getInstanceMetrics($instanceData),
                ...$this->getStatusMetrics($statusData),
                ...$this->getTaskMetrics($taskData)
            )
                        ->withHeader('Content-Type', 'text/plain; charset=utf-8')
                        ->respond();
        } catch (ConnectException $exception) {
            $ummonDown = GaugeCollection::fromGauges(
                MetricName::fromString('ummon_ok'),
                Gauge::fromValue(0)
                     ->withLabels($this->getInstanceLabel())
            )->withHelp('Is the server up?');
            HttpResponse::fromMetricCollections($ummonDown)
                        ->withHeader('Content-Type', 'text/plain; charset=utf-8')
                        ->respond();;
        }
    }

    private function getInstanceData(): stdClass
    {
        $response = $this->httpClient->get('');
        return $this->getJson($response);
    }

    private function getInstanceMetrics($instanceData): array
    {
        return [
            GaugeCollection::fromGauges(
                MetricName::fromString('ummon_version'),
                Gauge::fromValue(1)->withLabels(
                    $this->getInstanceLabel(),
                    Label::fromNameAndValue('version', $instanceData->version)
                )
            )->withHelp('the version string of the server'),
            GaugeCollection::fromGauges(
                MetricName::fromString('ummon_ok'),
                Gauge::fromValue($instanceData->ok)
                     ->withLabels($this->getInstanceLabel())
            )->withHelp('Is the server up?'),
        ];

    }

    private function getStatusData(): stdClass
    {
        $response = $this->httpClient->get('status');
        return $this->getJson($response);
    }

    private function getStatusMetrics($statusData): array
    {
        return [
            GaugeCollection::fromGauges(
                MetricName::fromString('ummon_current_workers'),
                Gauge::fromValue(count($statusData->workers))
                     ->withLabels($this->getInstanceLabel())
            )->withHelp('Number of tasks currently being run'),
            GaugeCollection::fromGauges(
                MetricName::fromString('ummon_max_workers'),
                Gauge::fromValue($statusData->maxWorkers)
                     ->withLabels($this->getInstanceLabel())
            )->withHelp('Max workers available'),
            GaugeCollection::fromGauges(
                MetricName::fromString('ummon_queue_length'),
                Gauge::fromValue(count($statusData->queue))
                     ->withLabels($this->getInstanceLabel())
            )->withHelp('Number of tasks waiting in the queue'),
            GaugeCollection::fromGauges(
                MetricName::fromString('ummon_is_paused'),
                Gauge::fromValue($statusData->isPaused)
                     ->withLabels($this->getInstanceLabel())
            )->withHelp('Is the server paused?'),
        ];
    }

    private function getTaskData(): stdClass
    {
        $response = $this->httpClient->get('tasks');
        return $this->getJson($response);
    }

    private function getTaskMetrics($taskData): array
    {
        $lastSuccessfulRun = GaugeCollection::withMetricName(MetricName::fromString('ummon_task_last_successful_run'))->withHelp('Unix Timestamp for the last time a task was successfully run');
        $lastExitStatus = GaugeCollection::withMetricName(MetricName::fromString('ummon_task_last_exit_status'))->withHelp('Exit code from the last run of the task');
        $successfulRuns = CounterCollection::withMetricName(MetricName::fromString('ummon_task_successful_runs'))->withHelp('Cumulative count of successful runs of a task since last reboot of ummon-server');
        $failedRuns = CounterCollection::withMetricName(MetricName::fromString('ummon_task_failed_runs'))->withHelp('Cumulative count of failed runs of a task since last reboot of ummon-server');

        foreach ($taskData->collections as $collection) {
            foreach ($collection->tasks as $task) {
                $labels = LabelCollection::fromAssocArray([
                    'collection' => $collection->collection,
                    'task'       => $task->id,
                ]);
                $lastSuccessfulRun->add(
                    Gauge::fromValue($task->lastSuccessfulRun / 1000 ?: 0)
                         ->withLabels($this->getInstanceLabel())
                         ->withLabelCollection($labels)
                );
                if (property_exists($task, 'totalSuccessfulRuns')) {
                    $successfulRuns->add(
                        Counter::fromValue($task->totalSuccessfulRuns)
                               ->withLabels($this->getInstanceLabel())
                               ->withLabelCollection($labels)
                    );
                }
                if (property_exists($task, 'totalFailedRuns')) {
                    $failedRuns->add(
                        Counter::fromValue($task->totalFailedRuns)
                               ->withLabels($this->getInstanceLabel())
                               ->withLabelCollection($labels)
                    );
                }
                $recentExitCodes = $task->recentExitCodes;
                if (count($recentExitCodes) > 0) {
                    $gaugeValue = $recentExitCodes[count($recentExitCodes) - 1];
                    if (is_null($gaugeValue)) {
                        // The last exit status was null. Report it as 999
                        $gaugeValue = 999;
                    }
                    $lastExitStatus->add(
                        Gauge::fromValue($gaugeValue)
                               ->withLabels($this->getInstanceLabel())
                               ->withLabelCollection($labels)
                    );
                }
            }
        }

        return [
            $lastSuccessfulRun,
            $lastExitStatus,
            $successfulRuns,
            $failedRuns,
        ];
    }

    private function getInstanceLabel(): Label
    {
        return Label::fromNameAndValue('instance', $this->host);
    }

    /**
     * @param ResponseInterface $response
     *
     * @return stdClass
     */
    protected function getJson(ResponseInterface $response)
    {
        return json_decode($response->getBody()->getContents());
    }
}
