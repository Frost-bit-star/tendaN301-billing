# Tenda Micro-ISP Router Project
## inside the device

![tenda internals](./tenda-inside.jpg)

this document focuses on the practical outcome and observations.  
for a detailed, step-by-step technical breakdown of the reverse-engineering process, see  
[full Docs](./Reverse-process.md)

**Router Device List**  
![Device List](https://github.com/Frost-bit-star/tendaN301-billing/blob/main/Screenshot%20(31).png)

![PHP](https://img.shields.io/badge/PHP-7.4+-blue) ![SQLite](https://img.shields.io/badge/SQLite-supported-orange) ![License](https://img.shields.io/badge/License-MIT-green) ![Build Status](https://img.shields.io/badge/Status-Active-brightgreen)

This project converts a Tenda router into a micro-ISP-style router by interacting with its internal API, reversing its firmware behavior, and using blocklists to control device internet access. Built in **PHP**, it uses **SQLite** to store router configurations.

---

## Table of Contents

1. [Project Overview](#project-overview)  
2. [Features](#features)  
3. [Architecture](#architecture)  
4. [Requirements](#requirements)  
5. [Installation](#installation)  
6. [Configuration](#configuration)  
7. [Usage](#usage)  
8. [Dashboard Screenshots](#dashboard-screenshots)  
9. [Security Considerations](#security-considerations)  
10. [File Structure](#file-structure)  
11. [License](#license)  

---

## Project Overview

This project allows centralized control over multiple Tenda routers. It fetches connected devices, identifies online and blacklisted devices, and applies rules to allow or restrict internet access. Essentially, it turns Tenda routers into lightweight managed routers for small-scale ISP setups or network labs.

---

## Features

- Automatic login to Tenda routers using stored credentials  
- Fetches online devices and connection types (wired/wireless)  
- Fetches blacklisted devices  
- Allows managing which devices have unrestricted internet access  
- Returns structured JSON of devices and internet access status  
- Supports multiple routers via SQLite database  

---

## Architecture

1. **Frontend**: Sends a request with a router ID.  
2. **Backend**: PHP script (`/auth/login.php`)  
   - Looks up router info in `/db/routers.db`  
   - Logs into router via API  
   - Fetches QoS data (online and blacklisted devices)  
   - Applies allow/block rules  
   - Returns JSON of devices  
3. **Database**: SQLite stores router credentials.  
4. **Logs**: Stores runtime logs in `/logs`  

---

## Requirements

- PHP 7.4+ with `pdo_sqlite` and `curl` extensions  
- Tenda router model N301  
- Web server (Apache/Nginx optional, or PHP built-in server)  

---

## Installation

### Step 1: Clone the repository

```bash
git clone https://github.com/Frost-bit-star/tendaN301-billing.git
cd tendaN301-billing
```
**Step 2: Install PHP and required extensions**
```
sudo apt update
sudo apt install -y php php-cli php-sqlite3 php-curl unzip
```
Step 3: Build and run the project
# Install project dependencies (if any)
```
php stack install

# Build the project
php stack build

# Start the server
php stack start
```

Once started, the server will be active on http://localhost:8000
.

## Configuration

Add your Tenda router credentials to add router on admin panel

Configure blocklists and rules in the SQLite database.

## Usage

- Access the web dashboard via [http://localhost:8000](http://localhost:8000).  
- Fetch online devices, apply allow/block rules, and view logs in real-time.  

### Dashboard Screenshots

**Dashboard Overview**  
![Dashboard](https://github.com/Frost-bit-star/tendaN301-billing/blob/main/Screenshot%20(30).png)


## Security Considerations

- Router credentials are stored in SQLite—ensure proper file permissions.  
- Use HTTPS when exposing the dashboard externally.  


### Regularly update PHP and server packages to minimize vulnerabilities.


