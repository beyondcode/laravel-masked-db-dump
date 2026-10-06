---
title: Dumping the Database
order: 3
---
# Dumping the Database

After you have configured your dump schema, it's time to dump your tables. This can be done using the `db:masked-dump` artisan command.
The command expects one argument, which is the name of the output file to use.

```
php artisan db:masked-dump output.sql
```

Running this command, will use the `default` dump schema definition and write the resulting dump to a file called `output.sql`.
The dump is written as it is generated, to a `.part` file next to the output file, which then replaces the output file once the dump is complete. If the dump fails or is interrupted, the output file is left untouched. An interrupted dump (Ctrl+C, memory limit) may leave the `.part` file behind, which the next dump overwrites.

## Changing Definitions

In case that your configuration file contains multiple dump schema definitions, you can pass the definition to use to the command like this:

```
php artisan db:masked-dump output.sql --definition=sqlite
```

## GZip compression

The default output is a plain text file - depending on the size of your dump, you might want to enable GZip compression. This can be done by passing the `--gzip` flag to the command:

```
php artisan db:masked-dump output.sql --gzip
```

This will write the compressed output to a file called `output.sql.gz`.