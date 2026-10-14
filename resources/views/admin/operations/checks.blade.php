<x-workspace.table :columns="['Check', 'Status', 'Detail']">
    @foreach ($checks as $check)
        <tr class="border-b border-sand-200 last:border-b-0">
            <th scope="row" class="px-4 py-4 font-medium text-lamu-900">{{ $check['name'] }}</th>
            <td class="px-4 py-4">@include('admin.operations.status', ['status' => $check['status'], 'label' => $check['label']])</td>
            <td class="px-4 py-4 text-sm text-stone-700">{{ $check['detail'] }}</td>
        </tr>
    @endforeach
</x-workspace.table>
