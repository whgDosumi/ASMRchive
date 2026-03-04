# Ephemeral ARM Jenkins Node Configuration

This document outlines the deployment of a temporary ARM64 Jenkins node on AWS EC2. The node is configured to tunnel all outbound build traffic (so, yt-dlp) through a Windows 11 Tailscale exit node to bypass cloud IP filtering, while maintaining symmetric routing so the Jenkins controller (and I) can still access it directly over SSH via its public ip (because I didn't want to install tailscale on the Jenkins server).

## Phase 1: Exit Node Preparation
1. Ensure **Tailscale** is installed and running on home PC. Below instructions are for Windows 11
2. Right-click the Tailscale tray icon -> **Exit Node** -> **Run as exit node**.
3. Log into the [Tailscale Admin Console](https://login.tailscale.com/admin/machines).
4. Locate the machine, click the three dots, select **Edit route settings**, and approve the exit node.
5. Note the Windows 11 machine's Tailscale IP (e.g., `100.x.x.x`).

## Phase 2: EC2 Provisioning
1. Log into the AWS Console and launch a new EC2 instance.
2. **AMI:** Select a modern ubuntu server that supports ARM.
3. **Architecture:** Select 64-bit ARM
4. **Instance Type:** `t4g.small` (or larger, depending on build requirements).
5. **Key pair name:** I've configured the "Jenkins" key pair in AWS to be used by Jenkins as a secret.
6. **Security Group:** Add security groups that allow ssh and port 4445 to my home ip address.
7. **Storage:** I recommend 16gb of gp3 for this.
7. **Advanced Details -> User Data:** Paste the automated deployment script at the bottom of the document (ensure the `HOME_IP` variable is updated with the current home public IP).
8. Launch the instance and copy its AWS Public IPv4 address.

## Phase 3: Tunnel Activation
1. SSH into the new EC2 instance using its AWS Public IP:
   `ssh -i /path/to/key.pem ubuntu@<EC2_PUBLIC_IP>`
2. Bring Tailscale online and force outbound traffic through the Windows PC:
   `sudo tailscale up --exit-node=<WINDOWS_TAILSCALE_IP>`
3. Verify requests now flow through your tailscale exit node (this should output your exit node's public ip):
   `curl icanhazip.com`

## Phase 4: Jenkins Integration
1. Access the [primary Jenkins server](https://jenkins.wronghood.net)
2. Navigate to **Manage Jenkins > Nodes > New Node**.
3. **Name:** `aws-arm-ephemeral` (or similar)
4. **Type:** Permanent Agent
5. **Remote root directory:** `/home/ubuntu`
6. **Labels:** `arm`
7. **Usage:** Only build jobs with label expressions matching this node
8. **Launch method:** Launch agents via SSH
   * **Host:** `<EC2_PUBLIC_IP>`
   * **Credentials:** Select `ubuntu (An SSH key for accessing AWS instances.)`
   * **Host Key Verification Strategy:** Non verifying Verification Strategy
9. Save the node configuration. Jenkins will automatically connect, install the remoting agent via the pre-installed Java environment, and bring the node online.

## Run a build on the ARM node
1. Open the pipeline where you'd like to build on arm.
2. Ensure at least one build has ran on the local node.
3. Click on the latest build, select "replay"
4. Replace "agent any" with:
```
agent {
        label "arm"
    }
```
5. Click "run"
6. Verify the arm node is running by going to Jenkins home and ensuring the executor is active.

## Teardown
1. In the EC2 console, terminate (delete) the instance.
2. In the [primary Jenkins server](https://jenkins.wronghood.net), **Manage Jenkins > Nodes > <node> > Delete Agent**

## User Data Script:
```
#!/bin/bash

# 1. Update the system and install required packages
apt-get update
apt-get install -y openjdk-17-jre-headless podman

# 2. Install Tailscale 
# (The daemon will start automatically, but you will run 'tailscale up' via SSH later to authenticate)
curl -fsSL https://tailscale.com/install.sh | sh

# 3. Apply Asymmetric Routing Fixes for Jenkins SSH
# IMPORTANT: Replace the placeholder below with your actual home network's public IP address
HOME_IP="<YOUR_HOME_PUBLIC_IP>"

# Dynamically grab the default AWS gateway for the subnet
AWS_GW=$(ip route show default | awk '/default/ {print $3}')

# Dynamically grab the default network interface name (e.g., ens5)
DEFAULT_IFACE=$(ip route show default | awk '/default/ {print $5}')

# Add static route bypassing Tailscale for the home IP
ip route add $HOME_IP via $AWS_GW dev $DEFAULT_IFACE

# Add IP rule forcing the OS to respect the static route over Tailscale's routing table
ip rule add to $HOME_IP lookup main pref 2500
```