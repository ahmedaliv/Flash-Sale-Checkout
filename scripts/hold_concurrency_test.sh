#!/bin/bash

# step 1 : setup the db and seeders
php artisan migrate:fresh --seed > /dev/null 2>&1
echo "Database migrated and seeded"

# step 2: serve the laravel app in the background
php artisan serve --host=127.0.0.1 --port=8000 > /dev/null 2>&1 &
SERVER_PID=$!
sleep 2
echo "Laravel server started on http://127.0.0.1:8000"

# step 3: check stock before the test
echo ""
echo "Stock before test:"
curl -s http://127.0.0.1:8000/api/v1/products/1 | jq

# step 4: run apache benchmark once, redirect all output to a file
ab -n 10 -c 10 -v 2 -p ./scripts/payload.json -T application/json http://127.0.0.1:8000/api/v1/holds > ab_output.txt
echo "ApacheBench test finished"

# step 5: check stock after the test
echo ""
echo "Stock after test:"
curl -s http://127.0.0.1:8000/api/v1/products/1 | jq

# kill the server
kill $SERVER_PID
echo "Laravel server stopped"

# step 6: parse the file for status codes
SUCCESS=$(grep "HTTP/1.0 201" ab_output.txt | wc -l)
CONFLICT=$(grep "HTTP/1.0 409" ab_output.txt | wc -l)

echo ""
echo "==== Flash Sale Test Results ===="
echo "201 Created (successful holds): $SUCCESS"
echo "409 Conflict (stock exceeded): $CONFLICT"
echo "================================"
