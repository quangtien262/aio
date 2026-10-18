# F&B isolated MySQL gates

These scripts are executable verification tools, not evidence that the workload has been run or a production release is accepted. Use a dedicated local MySQL test server with an existing compatible core schema. Source core tables are inspected with `SHOW CREATE TABLE`; no source user rows are copied. Fresh MySQL installation must be validated separately from these existing-core upgrade-path gates.

Both scripts refuse to access a database without `--allow-isolated-mysql`. They reject non-loopback MySQL hosts and read/write-routed connections. Each run creates its own random `aio_fnb_smoke_*` or `aio_fnb_load_*` database, writes only synthetic fixtures there, then drops only the exact database it created. The MySQL user needs local CREATE/DROP DATABASE privileges. Do not point the scripts at a production server, proxy, or live replica.

```sh
php tests/Support/fnb-mysql-smoke.php --help
php -d memory_limit=512M tests/Support/fnb-mysql-smoke.php --allow-isolated-mysql
php tests/Support/fnb-mysql-concurrency.php --help
php -d memory_limit=512M tests/Support/fnb-mysql-concurrency.php --allow-isolated-mysql --outlets=2 --iterations=5 --workload=counter-sale
php -d memory_limit=512M tests/Support/fnb-mysql-concurrency.php --allow-isolated-mysql --outlets=10 --iterations=100 --workload=counter-sale
php -d memory_limit=512M tests/Support/fnb-mysql-concurrency.php --allow-isolated-mysql --outlets=50 --iterations=200 --workload=availability
```

The configured source connection supplies the local server credentials. Do not put passwords in command arguments or committed files. The helper creates a new scratch connection, verifies its database identity, and uses a random ownership marker. Child processes receive only that generated database and must prove its marker before commands execute. Sessions/cache/mail/queue use isolated non-delivering drivers. Configuration, marker, worker and connection errors stop the gate; finally blocks stop child processes and remove this run's release marker and scratch database.

The smoke gate checks install/enable/disable/re-enable, the exact 58-permission immutable current map, at least 183 F&B foreign keys and explicit new composite-FK contracts, plus the original mixed-tender/refund/compensation/consumption/receipt journey.

The concurrency gate uses independent PHP processes and connections. It checks:

1. An outlet can commit while another outlet holds a command transaction; the held transaction is released only after the independent command completes. Its timeout is a deadlock/progress guard, not a production latency SLO.
2. Two simultaneous requests with the same actor, terminal, key and payload mutate once and produce one replay.
3. Two different keys against the same expected aggregate version produce one success and one conflict.
4. One worker per outlet completes the chosen load without unexpected conflicts; audit verification still succeeds across all partitions.

`availability` measures command writes and audit partitions. `counter-sale` runs session → order → line → submit → preparing/ready/served → bill/finalize/plan → cash collection → same-key collection retry → session closure. It asserts payment count/amount, per-terminal cash totals and closed sessions. It uses synthetic cash tenders; no real transfer, provider, email or printer is contacted.

The summary prints the MySQL version, workload size, elapsed duration, command-call throughput and p50/p95/p99/max command latency. A retry is counted as a command call. These are domain-command measurements with live outlet/terminal authorization, not HTTP, login/MFA, browser, customer-perceived checkout, provider or physical-print measurements. Run two-browser operational UAT, MySQL lifecycle/crash/overnight scenarios, production-like backup/restore and agreed end-to-end SLA checks separately. Save the actual command, environment version and summary with release evidence; do not label a syntax check or `--help` run as a MySQL/load pass.
