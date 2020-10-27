build .built:
	./copy-plugin
	docker-compose run p73-m23 install-magento
	docker-compose stop
	docker-compose run p73-m23 composer require transbank/transbank-sdk
	docker-compose stop
	docker-compose run p73-m23 install-plugin
	docker-compose stop
	docker-compose run p73-m23 install-sampledata
	docker-compose stop
	touch .built

stop:
	docker-compose down
	cd docker/ && docker-compose down

start: .built
	docker-compose up

clean:
	docker-compose rm -vfs
	rm .built
