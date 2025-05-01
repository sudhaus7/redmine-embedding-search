# Redmine Search with embeddings

## Usage

### updating the index

```bash
./redminesimilarities redmine:sync -vvv
```
Without any other option it will sync from the last synced date. If no synced date is found in REDIS, it will sync and index all redmine issues.


### comparing today's issues against the index

```bash
./redminesimilarities check 2025-05-01
```

the date is optional, it will use the current date if necessary. The output will list the issue, issue subject and then in a table which closely matched issues can be found for the given issue.


see help for more options

## Requirements

- a Redis server with the JSON and Search 2.0 Plugins enabled
- either an Open-ai account with an API Key
- or a locally running embedding engine
- or access to an embedding engine accessible with the open-ai API
- a redmine instance. If it is a private instance a REST API Key is required

## Redis

For an easier installation of a full redis server it is recommended to run it in a docker container:

```bash
mkdir data; docker run --name redmine-redis-container -p 6378:6379 -v ./data:/data  -d redis/redis-stack-server:latest
```

This will run the full-stack Redis server on port 6378 and the data will be written into a local folder called data

## Installation

- check out this project
- copy config.dist.yaml to config.yaml
- edit config.yaml

### Openai example

```yaml
redmine:
  url: https://my.redmine/
  key: mykeyifneeded
openai:
    embeddingmodel: text-embedding-3-small
    key: "sk-proj-mysecretopenaikey"
    dimensions: 1536
redis:
  connect:
    host: 127.0.0.1
    port: 6378
    database: 0
  vectorkeyname: redmine
```

- redmine.url = your redmine
- redmine.key = optional key (if needed)

you can test the redmine connection with the command:

```bash
./redminesimilarities redmine:ping
```

which should create an output similar to this:

```
+------------------------+-------+
| Latest Issue ID        | 12082 |
| Total Number of Issues | 11336 |
+------------------------+-------+
```

## Locally hosted Embedding Engine example

It is recommended to use ollama to manage and run various AI models, including embedding models. It can be installed from here:

https://ollama.com/

The example below uses the snowflake-arctic-embed2 but any other embedding models can be used.
You will need to find out how many dimensions are produced, to configure the dimensions in the yaml file. Some models allow the amount of dimensions to be configured.
It is recommended to use a model for large text analysis.
The model can be started with this command:

```bash
ollama pull snowflake-arctic-embed2
```

modifications to the config.yaml:

```yaml
redmine:
    url: https://my.redmine/
    key: mykeyifneeded
openai:
  embeddingmodel: snowflake-arctic-embed2
  baseuri: http://localhost:11434/v1
  dimensions: 1024
redis:
  connect:
    host: 127.0.0.1
    port: 6378
    database: 0
  vectorkeyname: redmine-snowflake
```

## Other config options

### redis.vectorkeyname

This is the namebase by which the entries will be written to redis and by which the Index will be created.

The created embeddings are cached to the harddrive in the .cache folder.

