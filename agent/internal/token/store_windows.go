//go:build windows

package token

import (
	"fmt"
	"os"
	"path/filepath"

	"github.com/billgraziano/dpapi"

	"github.com/syahrullrmdhn/sentraguard/agent/internal/config"
)

// windowsStore stores the runtime token as a DPAPI-encrypted file under
// ProgramData.
//
// WHY A FILE, NOT CREDENTIAL MANAGER:
// Install runs as the interactive admin user; the Windows Service runs as
// LocalSystem. Windows Credential Manager is per-user — a credential written
// by the admin is NOT visible to LocalSystem (lookup returns "Element not
// found"), so the service worker could never load the token. ProgramData is
// readable by every account on the host, and DPAPI machine-local encryption
// lets any principal on the same machine decrypt the blob. Together this lets
// the admin-run install hand the token to the LocalSystem service cleanly.
type windowsStore struct{}

// NewStore returns the production Windows-backed token store.
func NewStore() Store {
	return &windowsStore{}
}

// tokenPath is the on-disk location of the encrypted runtime token.
func tokenPath() string {
	return filepath.Join(config.DataDir(), "runtime.token")
}

func (s *windowsStore) Save(token string) error {
	encrypted, err := dpapi.EncryptMachineLocal(token)
	if err != nil {
		return fmt.Errorf("dpapi encrypt: %w", err)
	}

	path := tokenPath()
	if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
		return fmt.Errorf("create data dir: %w", err)
	}
	if err := os.WriteFile(path, []byte(encrypted), 0o600); err != nil {
		return fmt.Errorf("write token file: %w", err)
	}
	return nil
}

func (s *windowsStore) Load() (string, error) {
	data, err := os.ReadFile(tokenPath())
	if err != nil {
		return "", fmt.Errorf("read token file: %w", err)
	}
	decrypted, err := dpapi.Decrypt(string(data))
	if err != nil {
		return "", fmt.Errorf("dpapi decrypt: %w", err)
	}
	return decrypted, nil
}

func (s *windowsStore) Delete() error {
	err := os.Remove(tokenPath())
	if err != nil && !os.IsNotExist(err) {
		return fmt.Errorf("remove token file: %w", err)
	}
	return nil
}
