<?php

namespace HasanAlyazidi\DataTables\Tests\Fixtures;

use HasanAlyazidi\DataTables\Badge;
use HasanAlyazidi\DataTables\Column;
use HasanAlyazidi\DataTables\ColumnOrder;
use HasanAlyazidi\DataTables\DataTable;
use Illuminate\Database\Eloquent\Builder;

class UsersTestDataTable extends DataTable
{
    public function columns(): array
    {
        return [
            Column::id('test_users.id'),
            Column::make('name'),
            Column::make('email')->default('-'),
            Column::badge('status', [
                1 => [Badge::SUCCESS, 'Active'],
                0 => [Badge::DANGER, 'Blocked'],
            ]),
            Column::createdAt(),
            Column::actions()->render(function (TestUser $user) {
                return '<a href="#" class="edit-link">edit '.$user->id.'</a>';
            }),
        ];
    }

    public function title(): string
    {
        return 'Test Users';
    }

    protected function query(): Builder
    {
        return TestUser::query();
    }

    protected function filters(): array
    {
        return [
            'status' => function (Builder $query, $value) {
                $query->where('status', (int) $value);
            },
            'statuses' => function (Builder $query, $value) {
                $query->whereIn('status', (array) $value);
            },
        ];
    }

    protected function defaultOrder(): array
    {
        return [ColumnOrder::desc('id')];
    }
}
