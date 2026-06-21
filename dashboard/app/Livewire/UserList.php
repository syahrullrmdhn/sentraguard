<?php

namespace App\Livewire;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Livewire\WithPagination;

class UserList extends Component
{
    use WithPagination;

    public $search = '';
    public $roleFilter = '';
    
    // Modal state
    public $showModal = false;
    public $editMode = false;
    public $userId;
    
    // Form fields
    public $name = '';
    public $email = '';
    public $password = '';
    public $password_confirmation = '';
    public $role_id = '';

    protected function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . ($this->userId ?? 'NULL'),
            'password' => $this->editMode ? 'nullable|min:8|confirmed' : 'required|min:8|confirmed',
            'role_id' => 'required|exists:roles,id',
        ];
    }

    protected $messages = [
        'name.required' => 'Nama harus diisi.',
        'email.required' => 'Email harus diisi.',
        'email.email' => 'Format email tidak valid.',
        'email.unique' => 'Email sudah terdaftar.',
        'password.required' => 'Password harus diisi.',
        'password.min' => 'Password minimal 8 karakter.',
        'password.confirmed' => 'Konfirmasi password tidak cocok.',
        'role_id.required' => 'Role harus dipilih.',
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingRoleFilter()
    {
        $this->resetPage();
    }

    public function openCreate()
    {
        $this->reset(['name', 'email', 'password', 'password_confirmation', 'role_id', 'userId']);
        $this->editMode = false;
        $this->showModal = true;
    }

    public function openEdit($id)
    {
        $user = User::findOrFail($id);
        
        // Prevent editing yourself
        if ($user->id === auth()->id()) {
            session()->flash('error', 'Tidak dapat mengedit akun sendiri.');
            return;
        }

        $this->userId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role_id = $user->role_id;
        $this->password = '';
        $this->password_confirmation = '';
        $this->editMode = true;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        if ($this->editMode) {
            $user = User::findOrFail($this->userId);
            
            if ($user->id === auth()->id()) {
                session()->flash('error', 'Tidak dapat mengedit akun sendiri.');
                return;
            }

            $data = [
                'name' => $this->name,
                'email' => $this->email,
                'role_id' => $this->role_id,
            ];

            if ($this->password) {
                $data['password'] = Hash::make($this->password);
            }

            $user->update($data);
            session()->flash('success', 'User berhasil diperbarui.');
        } else {
            User::create([
                'name' => $this->name,
                'email' => $this->email,
                'password' => Hash::make($this->password),
                'role_id' => $this->role_id,
            ]);
            session()->flash('success', 'User berhasil ditambahkan.');
        }

        $this->reset(['showModal', 'name', 'email', 'password', 'password_confirmation', 'role_id', 'userId']);
    }

    public function delete($id)
    {
        $user = User::findOrFail($id);

        // Prevent deleting yourself
        if ($user->id === auth()->id()) {
            session()->flash('error', 'Tidak dapat menghapus akun sendiri.');
            return;
        }

        // Prevent deleting last Super Admin
        if ($user->role->name === 'super_admin' && User::whereHas('role', fn($q) => $q->where('name', 'super_admin'))->count() <= 1) {
            session()->flash('error', 'Tidak dapat menghapus Super Admin terakhir.');
            return;
        }

        $user->delete();
        session()->flash('success', 'User berhasil dihapus.');
    }

    public function render()
    {
        $users = User::with('role')
            ->when($this->search, fn($q) => $q->where('name', 'like', '%' . $this->search . '%')->orWhere('email', 'like', '%' . $this->search . '%'))
            ->when($this->roleFilter, fn($q) => $q->where('role_id', $this->roleFilter))
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $roles = Role::orderBy('display_name')->get();

        return view('livewire.user-list', compact('users', 'roles'));
    }
}
