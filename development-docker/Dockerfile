FROM alexcheng/magento2

RUN chsh -s /bin/bash www-data
COPY ./install-plugin /usr/local/bin/install-plugin
RUN chmod +x /usr/local/bin/install-plugin
