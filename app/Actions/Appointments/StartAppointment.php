<?php

namespace App\Actions\Appointments;

use App\Actions\ServiceOrders\SaveServiceOrder;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\ServiceOrder;
use App\Models\ServiceType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * « Commencer » : crée la prestation du jour pré-remplie (une ligne par prestation prévue, prix proposé),
 * lie le rendez-vous et le passe à Effectué. Verrou sur le rendez-vous : jamais deux prestations.
 */
class StartAppointment
{
    public function __construct(private SaveServiceOrder $save) {}

    public function handle(Appointment $appointment): ServiceOrder
    {
        return DB::transaction(function () use ($appointment) {
            $locked = Appointment::whereKey($appointment->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPlanned()) {
                throw ValidationException::withMessages(['appointment' => 'Ce rendez-vous est déjà '.mb_strtolower($locked->status->label()).'.']);
            }

            if ($locked->vehicle_id === null) {
                throw ValidationException::withMessages(['vehicle_id' => 'Choisissez d’abord le véhicule du rendez-vous.']);
            }

            $order = $this->save->handle(null, [
                'vehicle_id' => $locked->vehicle_id,
                'date' => today()->toDateString(),
                'notes' => $locked->notes,
            ], $locked->serviceTypes()->orderBy('name')->get()->map(fn (ServiceType $type) => [
                'service_type_id' => $type->id,
                'labor_price' => $type->default_labor_price,
            ])->all());

            $locked->forceFill(['status' => AppointmentStatus::Done, 'service_order_id' => $order->id])->save();
            $appointment->setRawAttributes($locked->getAttributes(), true);

            return $order;
        });
    }
}
