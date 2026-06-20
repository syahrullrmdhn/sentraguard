//go:build windows

package token

import (
	"fmt"

	"github.com/billgraziano/dpapi"
	"github.com/danieljoos/wincred"
)

// windowsStore stores the runtime token in Windows Credential Manager, with the
// blob encrypted at rest via DPAPI (machine scope).
type windowsStore struct{}

// NewStore returns the production Windows-backed token store.
func NewStore() Store {
	return &windowsStore{}
}

func (s *windowsStore) Save(token string) error {
	encrypted, err := dpapi.Encrypt(token)
	if err != nil {
		return fmt.Errorf("dpapi encrypt: %w", err)
	}

	cred := wincred.NewGenericCredential(credTarget)
	cred.UserName = credUsername
	cred.CredentialBlob = []byte(encrypted)
	cred.Persist = wincred.PersistLocalMachine
	if err := cred.Write(); err != nil {
		return fmt.Errorf("wincred write: %w", err)
	}
	return nil
}

func (s *windowsStore) Load() (string, error) {
	cred, err := wincred.GetGenericCredential(credTarget)
	if err != nil {
		return "", fmt.Errorf("wincred get: %w", err)
	}
	decrypted, err := dpapi.Decrypt(string(cred.CredentialBlob))
	if err != nil {
		return "", fmt.Errorf("dpapi decrypt: %w", err)
	}
	return decrypted, nil
}

func (s *windowsStore) Delete() error {
	cred, err := wincred.GetGenericCredential(credTarget)
	if err != nil {
		return nil // already absent
	}
	return cred.Delete()
}
