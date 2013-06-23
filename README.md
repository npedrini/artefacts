Artefacts of the Collective Unconscious
===============================
---
Artefacts of the Collective Unconscious is an experimental web project that visualizes user-submitted dreams to playfully suggest a hive mind at work, informing our dreamtime. 

The site uses the free [AlchemyAPI](http://www.alchemyapi.com/) to run sentiment analysis on the submitted dream descriptions, and the extracted tags are rendered as nodes along with the dreams themselves using D3. 

The project requires a server running PHP 5 and MySQL.

Installation instructions:
- Clone the repo and extract
- Create a local or remote mysql db for the project and import the sql dump located at `config/schema.sql`
- Open `config/config.php` and set the db connection information and any other configuration variables
- Move the extracted folder to a local or remote web server
    - NOTE: the `import` and `dummy_data` directories are only necessary if you plan on importing sample data
- Optionally, import the dreams at `dummy_data/dreams.csv` by visiting `http://[install location]/import/import_dreams.php`
