build .built:
	./copy-plugin
	docker-compose up --build -d p73-m23 
	docker-compose exec p73-m23 composer require transbank/transbank-sdk
	docker-compose exec p73-m23 install-plugin
	docker-compose stop
	touch .built

stop:
	docker-compose down
	cd docker/ && docker-compose down

start: .built
	docker-compose up

clean:
	docker-compose rm -vfs
	rm -f .built
