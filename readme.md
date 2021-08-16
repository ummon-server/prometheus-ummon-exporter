# Exporter for ummon data in prometheus format

This package uses the [ummon-server](https://github.com/ummon-server/ummon-server/) API to query for some statistics and outputs them in [OpenMetrics](https://github.com/OpenObservability/OpenMetrics) format to be scraped by [prometheus](https://prometheus.io/).

You can most conveniently fire it up as a [docker container](#with-docker).

## Usage

Using this exporter with Docker, you will need the hostname of your ummon instance and a username and password.

### with Docker

The image is based on `php:7.2-apache` and thus exposes data on port 80 by default. Assuming you fire this up with `-p 80:80` on localhost, you can see the metrics on http://localhost/metrics.

Configuration is done with 3 env variables: `UMMON_HOST`, `UMMON_USER` and `UMMON_PASSWORD`. `HTTP_PROTO` can optionally be passed; it defaults to `https`.

```shell
docker run -d --name ummon-prometheus \
  -e UMMON_HOST=ummon.example.com \
  -e UMMON_USER=monitoring \
  -e UMMON_PASSWORD=xxxxx \
  -p "80:80" \
  ghcr.io/ummon-server/prometheus-ummon-exporter:latest
```
### with docker-compose
```yaml
version: "2"

services:
  ummon_exporter:
    container_name: ummon_exporter
    image: ghcr.io/ummon-server/prometheus-ummon-exporter:latest
    restart: always
    hostname: ummon_exporter
    ports:
      - "8001:80"
    environment:
      - UMMON_HOST=ummon.example.com
      - UMMON_USER=monitoring
      - UMMON_PASSWORD=xxxxx
```

View on [Github Packages](https://github.com/ummon-server/prometheus-ummon-exporter/pkgs/container/prometheus-ummon-exporter)

## Output

The script will generate something like:

```prometheus
# TYPE ummon_current_workers gauge
# HELP ummon_current_workers Number of tasks currently being run
ummon_current_workers 10.000000
# TYPE ummon_max_workers gauge
# HELP ummon_max_workers Max workers available
ummon_max_workers 10.000000
# TYPE ummon_queue_length gauge
# HELP ummon_queue_length Number of tasks waiting in the queue
ummon_queue_length 44.000000
# TYPE ummon_is_paused gauge
# HELP ummon_is_paused Is the server paused?
ummon_is_paused 0.000000
# TYPE ummon_task_last_successful_run gauge
# HELP ummon_task_last_successful_run Unix Timestamp for the last time a task was successfully run
ummon_task_last_successful_run{task="collection1.task1", collection="collection1"} 1625096641.030000
ummon_task_last_successful_run{task="collection1.task2", collection="collection1"} 1625097639.502000
# TYPE ummon_task_last_exit_status gauge
# HELP ummon_task_last_exit_status Exit code from the last run of the task
ummon_task_last_exit_status{collection="collection1", task="collection1.task1"} 0.000000
ummon_task_last_exit_status{collection="collection1", task="collection1.task2"} 1.000000
# TYPE ummon_task_successful_runs counter
# HELP ummon_task_successful_runs Cumulative count of successful runs of a task since last reboot of ummon-server
ummon_task_successful_runs_total{task="collection1.task1", collection="collection1"} 2.000000
ummon_task_successful_runs_total{task="collection1.task2", collection="collection1"} 7.000000
# TYPE ummon_task_failed_runs counter
# HELP ummon_task_failed_runs Cumulative count of failed runs of a task since last reboot of ummon-server
ummon_task_failed_runs_total{task="collection1.task1", collection="collection1"} 0.000000
ummon_task_failed_runs_total{task="collection1.task2", collection="collection1"} 0.000000
```

## Development
To run in development:

```shell
composer install
docker run --rm \
  -e UMMON_HOST=ummon.example.com \
  -e UMMON_USER=monitoring \
  -e UMMON_PASSWORD=xxxxx \
  -v $PWD:/var/www/html \
  -p "8001:80" \
  php:7.2-apache
```


## Credits
Thanks to [ujamii/prometheus-sentry-exporter](https://github.com/ujamii/prometheus-sentry-exporter) which served as the inspiration and skeleton for this exporter.
