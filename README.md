# tableau-server-writer
KBC Writer to Tableau Server

The Writer takes files from Storage API File Upload tagged by `tde`, `table-export` and runId tags and uploads them
to Tableau server as datasources. TDE files can be prepared to File Upload using TDE Exporter first
(see https://github.com/keboola/docker-tde-exporter).

## Configuration

- **username** - Username of Tableau user account
- **password** - Password of Tableau user account
- **server_url** - Url of Tableau server
- **site** *(optional)* - Name of site to which the files will be uploaded
- **project_id** *(optional)* - Id of project to which the files will be uploaded
- **project_name** *(optional)* - Name of project to which the files will be uploaded

## API

