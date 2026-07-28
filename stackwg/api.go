package main

import (
	"encoding/json"
	"fmt"
	"log"
	"net/http"
	"strings"
	"time"
)

type API struct {
	wg   *WgServer
	store *PeerStore
	cfg  *Config
}

func NewAPI(wg *WgServer, store *PeerStore, cfg *Config) *API {
	return &API{wg: wg, store: store, cfg: cfg}
}

func (a *API) Start() {
	mux := http.NewServeMux()

	mux.HandleFunc("/api/health", a.handleHealth)
	mux.HandleFunc("/api/status", a.handleStatus)
	mux.HandleFunc("/api/peers", a.handlePeers)
	mux.HandleFunc("/api/peer/add", a.handlePeerAdd)
	mux.HandleFunc("/api/peer/remove", a.handlePeerRemove)
	mux.HandleFunc("/api/server_key", a.handleServerKey)
	mux.HandleFunc("/api/configure", a.handleConfigure)

	handler := corsMiddleware(logMiddleware(mux))

	log.Printf("[api] listening on %s", a.cfg.APIListen)
	if err := http.ListenAndServe(a.cfg.APIListen, handler); err != nil {
		log.Fatalf("[api] server failed: %v", err)
	}
}

func corsMiddleware(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Access-Control-Allow-Origin", "*")
		w.Header().Set("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
		w.Header().Set("Access-Control-Allow-Headers", "Content-Type")
		if r.Method == "OPTIONS" {
			w.WriteHeader(200)
			return
		}
		next.ServeHTTP(w, r)
	})
}

func logMiddleware(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		start := time.Now()
		next.ServeHTTP(w, r)
		log.Printf("[api] %s %s %s", r.Method, r.URL.Path, time.Since(start))
	})
}

func jsonResp(w http.ResponseWriter, code int, data interface{}) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(code)
	json.NewEncoder(w).Encode(data)
}

func jsonErr(w http.ResponseWriter, code int, msg string) {
	jsonResp(w, code, map[string]string{"error": msg})
}

func (a *API) handleHealth(w http.ResponseWriter, r *http.Request) {
	jsonResp(w, 200, map[string]string{"status": "ok"})
}

func (a *API) handleServerKey(w http.ResponseWriter, r *http.Request) {
	pub := a.wg.PublicKey()
	if pub == "" {
		jsonErr(w, 500, "server key not initialized")
		return
	}
	jsonResp(w, 200, map[string]string{
		"public_key": pub,
		"listen_port": fmt.Sprintf("%d", a.cfg.ListenPort),
	})
}

func (a *API) handleStatus(w http.ResponseWriter, r *http.Request) {
	status, err := a.wg.GetStatus()
	if err != nil {
		jsonErr(w, 500, err.Error())
		return
	}

	// Merge store metadata into peer status
	storedPeers := a.store.All()
	metaMap := make(map[string]*PeerRecord)
	for _, sp := range storedPeers {
		metaMap[sp.PublicKey] = sp
	}

	type enrichedPeer struct {
		PeerStatus
		DeviceID   string `json:"device_id,omitempty"`
		DeviceName string `json:"device_name,omitempty"`
		AddedAt    string `json:"added_at,omitempty"`
	}

	var peers []enrichedPeer
	for _, p := range status.Peers {
		ep := enrichedPeer{PeerStatus: p}
		if meta, ok := metaMap[p.PublicKey]; ok {
			ep.DeviceID = meta.DeviceID
			ep.DeviceName = meta.DeviceName
			ep.AddedAt = meta.AddedAt.Format(time.RFC3339)
		}
		peers = append(peers, ep)
	}

	jsonResp(w, 200, map[string]interface{}{
		"server_public_key": a.wg.PublicKey(),
		"private_key_set":   status.PrivateKeySet,
		"listen_port":       status.ListenPort,
		"fwmark":            status.FwMark,
		"subnet":            a.cfg.Subnet,
		"peers":             peers,
		"peer_count":        len(peers),
	})
}

func (a *API) handlePeers(w http.ResponseWriter, r *http.Request) {
	status, err := a.wg.GetStatus()
	if err != nil {
		jsonErr(w, 500, err.Error())
		return
	}

	storedPeers := a.store.All()
	metaMap := make(map[string]*PeerRecord)
	for _, sp := range storedPeers {
		metaMap[sp.PublicKey] = sp
	}

	type peerInfo struct {
		PeerStatus
		DeviceID   string `json:"device_id,omitempty"`
		DeviceName string `json:"device_name,omitempty"`
		AddedAt    string `json:"added_at,omitempty"`
	}

	var peers []peerInfo
	for _, p := range status.Peers {
		pi := peerInfo{PeerStatus: p}
		if meta, ok := metaMap[p.PublicKey]; ok {
			pi.DeviceID = meta.DeviceID
			pi.DeviceName = meta.DeviceName
			pi.AddedAt = meta.AddedAt.Format(time.RFC3339)
		}
		peers = append(peers, pi)
	}

	jsonResp(w, 200, map[string]interface{}{
		"peers":      peers,
		"peer_count": len(peers),
	})
}

type peerAddReq struct {
	PublicKey   string `json:"public_key"`
	AllowedIP   string `json:"allowed_ip"`
	PresharedKey string `json:"preshared_key,omitempty"`
	DeviceID    string `json:"device_id,omitempty"`
	DeviceName  string `json:"device_name,omitempty"`
}

func (a *API) handlePeerAdd(w http.ResponseWriter, r *http.Request) {
	if r.Method != "POST" {
		jsonErr(w, 405, "POST required")
		return
	}

	var req peerAddReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		jsonErr(w, 400, "invalid JSON: "+err.Error())
		return
	}

	req.PublicKey = strings.TrimSpace(req.PublicKey)
	req.AllowedIP = strings.TrimSpace(req.AllowedIP)

	if req.PublicKey == "" {
		jsonErr(w, 400, "public_key required")
		return
	}
	if req.AllowedIP == "" {
		jsonErr(w, 400, "allowed_ip required (e.g. 10.100.0.2/32)")
		return
	}

	// Validate hex key
	if _, err := ValidateHexKey(req.PublicKey); err != nil {
		jsonErr(w, 400, "invalid public_key: "+err.Error())
		return
	}

	// Add to WireGuard device (also adds to peer store)
	if err := a.wg.AddPeer(req.PublicKey, req.AllowedIP, req.PresharedKey); err != nil {
		jsonErr(w, 500, "add peer to device: "+err.Error())
		return
	}

	// Update metadata and persist
	rec := a.store.Get(req.PublicKey)
	if rec != nil {
		rec.DeviceID = req.DeviceID
		rec.DeviceName = req.DeviceName
	}
	if err := a.store.Save(); err != nil {
		log.Printf("[api] WARNING: failed to persist peer: %v", err)
	}

	log.Printf("[api] peer added: %s → %s (device=%s)", req.PublicKey[:8]+"...", req.AllowedIP, req.DeviceID)

	jsonResp(w, 200, map[string]interface{}{
		"success":     true,
		"public_key":  req.PublicKey,
		"allowed_ip":  req.AllowedIP,
		"device_id":   req.DeviceID,
	})
}

func (a *API) handlePeerRemove(w http.ResponseWriter, r *http.Request) {
	if r.Method != "POST" {
		jsonErr(w, 405, "POST required")
		return
	}

	var req struct {
		PublicKey string `json:"public_key"`
	}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		jsonErr(w, 400, "invalid JSON")
		return
	}

	req.PublicKey = strings.TrimSpace(req.PublicKey)
	if req.PublicKey == "" {
		jsonErr(w, 400, "public_key required")
		return
	}

	if err := a.wg.RemovePeer(req.PublicKey); err != nil {
		jsonErr(w, 500, "remove peer: "+err.Error())
		return
	}

	if err := a.store.Save(); err != nil {
		log.Printf("[api] WARNING: failed to persist peer removal: %v", err)
	}

	log.Printf("[api] peer removed: %s", req.PublicKey[:8]+"...")

	jsonResp(w, 200, map[string]interface{}{
		"success":    true,
		"public_key": req.PublicKey,
	})
}

func (a *API) handleConfigure(w http.ResponseWriter, r *http.Request) {
	if r.Method != "POST" {
		jsonErr(w, 405, "POST required")
		return
	}

	var req struct {
		PrivateKey string `json:"private_key"`
		ListenPort int    `json:"listen_port"`
	}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		jsonErr(w, 400, "invalid JSON")
		return
	}

	if req.PrivateKey != "" {
		if _, err := ValidateHexKey(req.PrivateKey); err != nil {
			jsonErr(w, 400, "invalid private_key: "+err.Error())
			return
		}
		if err := a.wg.Configure(req.PrivateKey, a.cfg.ListenPort); err != nil {
			jsonErr(w, 500, "configure: "+err.Error())
			return
		}
		// Save
		keyFile := a.cfg.DataDir + "/server_private.key"
		if err := writeFile(keyFile, []byte(req.PrivateKey), 0600); err != nil {
			log.Printf("[api] WARNING: could not save key: %v", err)
		}
		log.Printf("[api] server private key updated")
	}

	if req.ListenPort > 0 {
		a.cfg.ListenPort = req.ListenPort
		log.Printf("[api] listen port set to %d (restart required to apply)", req.ListenPort)
	}

	jsonResp(w, 200, map[string]interface{}{
		"success":         true,
		"server_public_key": a.wg.PublicKey(),
	})
}

