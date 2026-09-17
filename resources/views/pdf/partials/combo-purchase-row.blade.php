<tr>
    <td>{{ $purchase->id }}</td><td>{{ $purchase->msisdn }}</td><td>{{ $purchase->package }}</td>
    <td>{{ \Carbon\Carbon::parse($purchase->purchase_date)->format('d M Y, H:i') }}</td>
    <td>{{ \Carbon\Carbon::parse($purchase->expiry_date)->format('d M Y, H:i') }}</td>
    <td>{{ number_format($purchase->price, 2) }}</td><td>{{ $purchase->status }}</td>
</tr>
