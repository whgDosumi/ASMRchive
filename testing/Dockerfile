# Runs a headless chrome for testing purposes. 
FROM almalinux:latest

RUN dnf install -y epel-release
RUN dnf clean all
RUN dnf -y update
RUN dnf install -y python pip wget unzip
# Install chrome
RUN wget https://dl.google.com/linux/linux_signing_key.pub
RUN update-crypto-policies --set DEFAULT:SHA1
RUN rpm --import linux_signing_key.pub
RUN update-crypto-policies --set DEFAULT
RUN wget https://dl.google.com/linux/direct/google-chrome-stable_current_x86_64.rpm
RUN dnf clean all
RUN dnf -y localinstall google-chrome-stable_current_x86_64.rpm --skip-broken --setopt=install_weak_deps=False


RUN mkdir /test
RUN mkdir /test/downloads
COPY ./* /test/
RUN pip install -r /test/requirements.txt
ENTRYPOINT ["python", "/test/test.py"]