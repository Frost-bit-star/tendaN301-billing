package main

import (
	"fmt"
	"log"
	"os"
	"os/signal"
	"syscall"
)

func main() {
	log.SetFlags(log.LstdFlags | log.Lmicroseconds)
	log.Println("[main] stackwg - userspace WireGuard server")

	cfg := loadConfig()
	log.Printf("[main] config: iface=%s port=%d addr=%s api=%s",
		cfg.InterfaceName, cfg.ListenPort, cfg.Address, cfg.APIListen)

	// Initialize peer store and load persisted peers
	store := NewPeerStore(cfg.DataDir)
	if err := store.Load(); err != nil {
		log.Printf("[main] WARNING: could not load peers: %v", err)
	}

	// Initialize WireGuard server (needs store for peer management)
	wg, err := NewWgServer(cfg, store)
	if err != nil {
		log.Fatalf("[main] failed to create WireGuard server: %v", err)
	}

	// Load or generate private key
	privKey, err := wg.LoadSavedPrivateKey()
	if err != nil {
		log.Fatalf("[main] private key: %v", err)
	}

	// Configure the WireGuard device
	if err := wg.Configure(privKey, cfg.ListenPort); err != nil {
		log.Fatalf("[main] configure device: %v", err)
	}

	log.Printf("[main] server public key: %s", wg.PublicKey())

	// Apply all persisted peers to the WireGuard device
	if err := wg.ApplyAllPeers(); err != nil {
		log.Printf("[main] WARNING: failed to apply peers: %v", err)
	}
	for _, rec := range store.All() {
		log.Printf("[main] restored peer %s → %s (device=%s)",
			rec.PublicKey[:8]+"...", rec.AllowedIP, rec.DeviceID)
	}

	// Set up NAT / internet forwarding
	setupNAT(cfg)

	// Start API server
	api := NewAPI(wg, store, cfg)
	go api.Start()

	// Save public key for the PHP app
	pubKeyFile := cfg.DataDir + "/server_wg_pubkey.txt"
	if err := writeFile(pubKeyFile, []byte(wg.PublicKey()), 0644); err != nil {
		log.Printf("[main] WARNING: could not save public key file: %v", err)
	} else {
		log.Printf("[main] public key saved to %s", pubKeyFile)
	}

	// Also save to a JSON status file for easy access
	statusFile := cfg.DataDir + "/wg_status.json"
	statusJSON := fmt.Sprintf(`{"server_public_key":"%s","listen_port":%d,"subnet":"%s","api":"%s"}`,
		wg.PublicKey(), cfg.ListenPort, cfg.Subnet, cfg.APIListen)
	if err := writeFile(statusFile, []byte(statusJSON), 0644); err != nil {
		log.Printf("[main] WARNING: could not save status: %v", err)
	}

	log.Println("[main] stackwg is running. Press Ctrl+C to stop.")

	// Wait for shutdown signal
	sigCh := make(chan os.Signal, 1)
	signal.Notify(sigCh, syscall.SIGINT, syscall.SIGTERM)
	sig := <-sigCh
	log.Printf("[main] received %s, shutting down...", sig)

	// Cleanup
	cleanupNAT(cfg)
	wg.Close()
	store.Save()
	log.Println("[main] stackwg stopped")
}
