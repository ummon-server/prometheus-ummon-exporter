<?php

namespace WayToHealth\OpenMetrics\Ummon;

use OpenMetricsPhp\Exposition\Text\Collections\GaugeCollection;
use OpenMetricsPhp\Exposition\Text\Collections\LabelCollection;
use OpenMetricsPhp\Exposition\Text\HttpResponse;
use OpenMetricsPhp\Exposition\Text\Metrics\Gauge;
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
     * @var \GuzzleHttp\Client
     */
    protected $httpClient;

    /**
     * @var array
     */
    protected $options = [];

    /**
     * UmmonExporter constructor.
     *
     * @param string $baseUri
     * @param string $user
     * @param string $password
     */
    public function __construct(string $baseUri, string $user, string $password)
    {
        $this->httpClient = new \GuzzleHttp\Client([
            'base_uri' => $baseUri,
            'auth'     => [$user, $password],
        ]);
        $this->options = [
            'auth' => [$user, $password],
        ];
    }

    public function run(): void
    {
        $statusData = $this->getStatusData();
        $taskData = $this->getTaskData();

        HttpResponse::fromMetricCollections(
            ...$this->getStatusMetrics($statusData),
            ...$this->getTaskMetrics($taskData)
        )
                    ->withHeader('Content-Type', 'text/plain; charset=utf-8')
                    ->respond();
        // var_dump($collections->collections[2]);
        // die();
    }

    private function getStatusMetrics($statusData): array
    {
        return [
            GaugeCollection::fromGauges(
                MetricName::fromString('ummon_current_workers'),
                Gauge::fromValue(count($statusData->workers))
            )->withHelp('Number of tasks currently being run'),
            GaugeCollection::fromGauges(
                MetricName::fromString('ummon_max_workers'),
                Gauge::fromValue($statusData->maxWorkers)
            )->withHelp('Max workers available'),
            GaugeCollection::fromGauges(
                MetricName::fromString('ummon_queue_length'),
                Gauge::fromValue(count($statusData->queue))
            )->withHelp('Number of tasks waiting in the queue'),
            GaugeCollection::fromGauges(
                MetricName::fromString('ummon_is_paused'),
                Gauge::fromValue($statusData->isPaused)
            )->withHelp('Is the server paused?'),
        ];
    }

    private function getStatusData(): stdClass
    {
        $response = $this->httpClient->get('status');
        return $this->getJson($response);
    }

    private function getTaskData(): stdClass
    {
        $response = $this->httpClient->get('tasks');
        return $this->getJson($response);
    }

    private function getTaskMetrics($taskData): array
    {
        $lastSuccessfulRun = GaugeCollection::withMetricName(MetricName::fromString('ummon_task_last_successful_run'))->withHelp('Unix Timestamp for the last time a task was successfully run');

        foreach ($taskData->collections as $collection) {
            foreach ($collection->tasks as $task) {
                $lastSuccessfulRun->add(
                    Gauge::fromValue($task->lastSuccessfulRun ?: 0)
                         ->withLabelCollection(
                             LabelCollection::fromAssocArray([
                                 'task'       => $task->id,
                                 'collection' => $collection->collection,
                             ])
                         )
                );
            }
        }

        return [
            $lastSuccessfulRun,
        ];
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
