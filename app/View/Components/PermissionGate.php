<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class PermissionGate extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(
        public string $permission,
        public ?string $model = null
    ) {
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|string
    {
        $user = auth()->user();

        if (!$user) {
            return '';
        }

        // Check if user has permission
        if ($this->model) {
            $model = $this->model;
            $hasPermission = $user->can($this->permission, $model);
        } else {
            $hasPermission = $user->can($this->permission);
        }

        if (!$hasPermission) {
            return '';
        }

        return view('components.permission-gate');
    }
}

