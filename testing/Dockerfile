FROM almalinux:latest

RUN dnf install -y epel-release \
    && dnf clean all \
    && dnf -y update \
    && dnf install -y python3 python3-pip chromium chromium-headless chromedriver \
    && dnf clean all

RUN mkdir -p /test/downloads

COPY requirements.txt /test/
RUN pip3 install --no-cache-dir -r /test/requirements.txt

COPY ./* /test/

ENTRYPOINT ["python3", "/test/test.py"]