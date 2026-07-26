<?php
class MikroTikAPI {
    private $sock = null;
    private $host;
    private $port;
    private $user;
    private $pass;

    public function __construct($host, $port = 8729, $user = 'jasiri-api', $pass = '') {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->pass = $pass;
    }

    public function connect() {
        $ctx = stream_context_create([
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true],
        ]);
        $this->sock = @stream_socket_client("tls://{$this->host}:{$this->port}", $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $ctx);
        if (!$this->sock) {
            $this->sock = @stream_socket_client("tcp://{$this->host}:{$this->port}", $errno, $errstr, 5);
        }
        if (!$this->sock) {
            throw new Exception("Cannot connect to {$this->host}:{$this->port} - $errstr ($errno)");
        }
        stream_set_timeout($this->sock, 5);
        $greeting = $this->readWord();
        $this->login();
        return true;
    }

    public function close() {
        if ($this->sock) { fclose($this->sock); $this->sock = null; }
    }

    private function login() {
        $response = $this->command(['/login']);
        if (isset($response[0]['ret'])) {
            $challenge = $response[0]['ret'];
            $md5 = md5("\0" . $this->pass . hex2bin($challenge));
            $this->command(['/login', "=name={$this->user}", "=response=00$md5"]);
        } else {
            $this->command(['/login', "=name={$this->user}", "=password={$this->pass}"]);
        }
    }

    public function command($words) {
        $this->sendSentence($words);
        return $this->readSentence();
    }

    private function sendSentence($words) {
        foreach ($words as $word) {
            $this->writeWord($word);
        }
        $this->writeWord('');
    }

    private function readSentence() {
        $results = [];
        $current = [];
        while (true) {
            $word = $this->readWord();
            if ($word === false) break;
            if ($word === '') {
                if (!empty($current)) {
                    $results[] = $current;
                    $current = [];
                }
                break;
            }
            if ($word[0] === '=' && isset($word[1]) && $word[1] === '!') {
                $key = substr($word, 2);
                $current['!error'] = $key;
            } elseif ($word[0] === '=') {
                $eqPos = strpos($word, '=');
                $key = substr($word, 1, $eqPos - 1);
                $val = substr($word, $eqPos + 1);
                $current[$key] = $val;
            } elseif ($word[0] === '.') {
                $current['_sentence'] = $word;
            } else {
                $current[] = $word;
            }
        }
        return $results;
    }

    private function writeWord($word) {
        $len = strlen($word);
        $this->writeLength($len);
        if ($len > 0) fwrite($this->sock, $word);
    }

    private function readWord() {
        $len = $this->readLength();
        if ($len === false) return false;
        if ($len === 0) return '';
        return fread($this->sock, $len);
    }

    private function writeLength($length) {
        if ($length < 128) {
            fwrite($this->sock, chr($length));
        } elseif ($length < 16384) {
            fwrite($this->sock, chr(($length & 0x7F) | 0x80) . chr(($length >> 7) & 0x7F));
        } else {
            $result = chr(($length & 0x7F) | 0x80);
            $length >>= 7;
            while ($length >= 128) {
                $result .= chr(($length & 0x7F) | 0x80);
                $length >>= 7;
            }
            $result .= chr($length);
            fwrite($this->sock, $result);
        }
    }

    private function readLength() {
        $byte = fread($this->sock, 1);
        if ($byte === false || $byte === '') return false;
        $value = ord($byte);
        if ($value & 0x80) {
            $result = $value & 0x7F;
            $multiplier = 128;
            while (true) {
                $byte = fread($this->sock, 1);
                if ($byte === false) return false;
                $value = ord($byte);
                $result += ($value & 0x7F) * $multiplier;
                $multiplier *= 128;
                if (!($value & 0x80)) break;
            }
            return $result;
        }
        return $value;
    }

    public function addHotspotUser($server, $username, $password, $limitUptime) {
        $result = $this->command([
            '/ip/hotspot/user/add',
            "=server=$server",
            "=name=$username",
            "=password=$password",
            "=limit-uptime=$limitUptime",
            "=comment=Jasiri voucher",
        ]);
        return $result;
    }

    public function removeHotspotUser($username) {
        $find = $this->command(['/ip/hotspot/user/find', "?name=$username"]);
        if (!empty($find)) {
            $id = $find[0] ?? $find;
            return $this->command(['/ip/hotspot/user/remove', "=.id=$id"]);
        }
        return null;
    }

    public function findHotspotActive($user) {
        return $this->command(['/ip/hotspot/active/find', "?user=$user"]);
    }

    public function disconnectHotspotActive($activeId) {
        return $this->command(['/ip/hotspot/active/remove', "=.id=$activeId"]);
    }

    public function getHotspotActiveCount() {
        $result = $this->command(['/ip/hotspot/active/print']);
        return $result;
    }

    public function removeHotspotUserByUsername($username) {
        $find = $this->command(['/ip/hotspot/user/find', "?name=$username"]);
        if (!empty($find)) {
            $id = $find[0]['.id'] ?? ($find[0] ?? null);
            if ($id) {
                return $this->command(['/ip/hotspot/user/remove', "=.id=$id"]);
            }
        }
        return null;
    }

    public function getWirelessInfo() {
        $interfaces = $this->command(['/interface/wireless/print']);
        $profiles = $this->command(['/interface/wireless/security-profiles/print']);
        return ['interfaces' => $interfaces, 'profiles' => $profiles];
    }

    public function setWireless($ssid, $securityProfile = null, $mode = 'ap-bridge') {
        $find = $this->command(['/interface/wireless/find', '']);
        if (!empty($find)) {
            $id = is_array($find[0]) ? ($find[0]['.id'] ?? '*0') : $find[0];
            $this->command(['/interface/wireless/set', "=.id=$id", "=mode=$mode", "=ssid=$ssid", "=disabled=no"]);
            if ($securityProfile) {
                $this->command(['/interface/wireless/set', "=.id=$id", "=security-profile=$securityProfile"]);
            }
        }
        return true;
    }

    public function setWirelessProfile($name, $authTypes = 'none', $enc = 'none') {
        $existing = $this->command(['/interface/wireless/security-profiles/find', "?name=$name"]);
        if (!empty($existing)) {
            $id = is_array($existing[0]) ? ($existing[0]['.id'] ?? '*0') : $existing[0];
            return $this->command(['/interface/wireless/security-profiles/set', "=.id=$id", "=authentication-types=$authTypes", "=unicast-cast-encryption=$enc"]);
        }
        return $this->command(['/interface/wireless/security-profiles/add', "=name=$name", "=authentication-types=$authTypes", "=unicast-cast-encryption=$enc"]);
    }

    public function setBridgePort($interface, $bridge) {
        $existing = $this->command(['/interface/bridge/port/find', "?interface=$interface"]);
        if (!empty($existing)) {
            $id = is_array($existing[0]) ? ($existing[0]['.id'] ?? '*0') : $existing[0];
            return $this->command(['/interface/bridge/port/set', "=.id=$id", "=bridge=$bridge"]);
        }
        return $this->command(['/interface/bridge/port/add', "=interface=$interface", "=bridge=$bridge"]);
    }
}
