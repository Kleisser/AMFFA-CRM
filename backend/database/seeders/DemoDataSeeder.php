<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\CallLog;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Goal;
use App\Models\Message;
use App\Models\Note;
use App\Models\Reminder;
use App\Models\Task;
use App\Models\Visit;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@amffa.com.ar')->first();
        $asesores = User::where('role', 'seller')->get();
        $supervisores = User::where('role', 'supervisor')->get();

        $stages = [1, 2, 3, 4, 5, 6];
        $sources = ['website', 'referral', 'facebook', 'instagram', 'whatsapp', 'call', 'email', 'walk_in', 'other'];

        $companies = [
            'TechCorp SRL', 'MegaSoft SA', 'DataFlow AR', 'CloudNet Latam',
            'Soluciones SA', 'Innovatec', 'Grupo Omega', 'AlphaBiz',
            'Nexo Digital', 'Pixel Studios', 'WebGen Argentina', 'SmartSys',
            'BlueOcean Tech', 'RedLine Corp', 'GreenField SA',
        ];

        $firstNames = [
            'Carlos', 'María', 'Juan', 'Ana', 'Pedro', 'Laura', 'Diego', 'Sofía',
            'Lucas', 'Valentina', 'Martín', 'Camila', 'Felipe', 'Lucía', 'Santiago',
            'Florencia', 'Matías', 'Julieta', 'Nicolás', 'Victoria', 'Alejandro',
            'Rocío', 'Gabriel', 'Agustina', 'Fernando', 'Constanza', 'Andrés',
            'Catalina', 'Pablo', 'Micaela',
        ];

        $lastNames = [
            'González', 'Rodríguez', 'López', 'Martínez', 'Pérez', 'García',
            'Fernández', 'Díaz', 'Moreno', 'Álvarez', 'Romero', 'Torres',
            'Ruiz', 'Flores', 'Acosta', 'Medina', 'Castillo', 'Ríos', 'Ortiz',
            'Morales', 'Silva', 'Campo', 'Vega', 'Cáceres', 'Pereyra',
        ];

        $contactIds = [];

        $demoLocalities = \App\Models\Locality::with('zone')->inRandomOrder()->limit(400)->get();
        $plans = \App\Models\Plan::where('is_active', true)->get();
        $quoteService = app(\App\Services\QuoteService::class);

        for ($i = 0; $i < 120; $i++) {
            $name = $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)];
            $stageId = $stages[array_rand($stages)];
            $asesor = $asesores->random();
            $source = $sources[array_rand($sources)];
            $daysAgo = rand(0, 60);
            $demoLocality = $demoLocalities->random();

            $plan = $plans->count() ? $plans->random() : null;
            $family = [];
            if ($plan) {
                $titularAge = rand(22, 65);
                $family[] = ['relation' => 'titular', 'age' => $titularAge];
                if (rand(0, 1)) {
                    $family[] = ['relation' => 'conyuge', 'age' => max(18, $titularAge + rand(-5, 5))];
                }
                $childCount = rand(0, 3);
                for ($c = 0; $c < $childCount; $c++) {
                    $family[] = ['relation' => 'hijo', 'age' => rand(1, 25)];
                }
            }

            $contact = Contact::create([
                'name' => $name,
                'dni' => (string) rand(10000000, 49999999),
                'email' => strtolower(str_replace(' ', '.', $name)) . '@email.com',
                'phone' => '11' . rand(20000000, 59999999),
                'company' => $companies[array_rand($companies)],
                'position' => ['CEO', 'Gerente', 'Analista', 'Director', 'Coordinador', 'Asistente'][array_rand(['CEO', 'Gerente', 'Analista', 'Director', 'Coordinador', 'Asistente'])],
                'source' => $source,
                'address' => 'Av. ' . ['Corrientes', 'Santa Fe', 'Córdoba', 'Callao', 'Rivadavia', 'Cabildo'][array_rand(['Corrientes', 'Santa Fe', 'Córdoba', 'Callao', 'Rivadavia', 'Cabildo'])] . ' ' . rand(100, 5000),
                'zone_id' => $demoLocality->zone_id,
                'locality_id' => $demoLocality->id,
                'plan_id' => $plan?->id,
                'pipeline_stage_id' => $stageId,
                'assigned_to' => $asesor->id,
                'created_by' => $asesor->id,
                'created_at' => now()->subDays($daysAgo),
                'updated_at' => now()->subDays(rand(0, $daysAgo)),
            ]);

            foreach ($family as $index => $member) {
                \App\Models\FamilyMember::create([
                    'contact_id' => $contact->id,
                    'relation' => $member['relation'],
                    'age' => $member['age'],
                    'sort_order' => $index,
                ]);
            }

            if ($plan) {
                $titular = $family[0]['age'] ?? null;
                $conyuge = collect($family)->firstWhere('relation', 'conyuge')['age'] ?? null;
                $hijos = collect($family)->where('relation', 'hijo')->pluck('age')->values()->all();
                $quote = $quoteService->calculate($plan, null, $titular, $conyuge, $hijos);
                $contact->updateQuietly(['deal_value' => $quote['total'] ?? null]);
            }

            $contactIds[] = $contact->id;

            ActivityLog::create([
                'user_id' => $asesor->id,
                'contact_id' => $contact->id,
                'action' => 'created',
                'description' => "Contacto {$contact->name} creado desde {$source}",
                'created_at' => $contact->created_at,
            ]);
        }

        foreach ($contactIds as $index => $cid) {
            if ($index % 3 !== 0) continue;

            $contact = Contact::find($cid);
            $stageId = $contact->pipeline_stage_id;

            $conversation = Conversation::create([
                'contact_id' => $cid,
                'assigned_to' => $contact->assigned_to,
                'channel' => ['whatsapp', 'email', 'facebook', 'instagram'][array_rand(['whatsapp', 'email', 'facebook', 'instagram'])],
                'status' => $stageId === 5 || $stageId === 6 ? 'closed' : 'open',
                'subject' => 'Conversación con ' . $contact->name,
                'last_message_at' => now()->subHours(rand(1, 72)),
            ]);

            $msgCount = rand(2, 8);
            for ($m = 0; $m < $msgCount; $m++) {
                Message::create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => $m % 2 === 0 ? $contact->assigned_to : null,
                    'direction' => $m % 2 === 0 ? 'outgoing' : 'incoming',
                    'content' => [
                        'Hola, ¿cómo estás?', 'Gracias por contactarnos',
                        'Queremos saber más sobre sus productos',
                        'Te envío la propuesta comercial',
                        'Perfecto, revisemos los detalles',
                        '¿Qué tal si agendamos una reunión?',
                        'Me interesa mucho su oferta',
                        'Quedamos en contacto, gracias',
                    ][array_rand([
                        'Hola, ¿cómo estás?', 'Gracias por contactarnos',
                        'Queremos saber más sobre sus productos',
                        'Te envío la propuesta comercial',
                        'Perfecto, revisemos los detalles',
                        '¿Qué tal si agendamos una reunión?',
                        'Me interesa mucho su oferta',
                        'Quedamos en contacto, gracias',
                    ])],
                    'type' => 'text',
                    'is_read' => (bool) rand(0, 1),
                    'created_at' => now()->subHours(rand(1, 72))->addMinutes($m * 5),
                ]);
            }

            ActivityLog::create([
                'user_id' => $contact->assigned_to,
                'contact_id' => $cid,
                'action' => 'conversation',
                'description' => "Conversación iniciada por {$conversation->channel}",
            ]);
        }

        $taskTemplates = [
            ['Llamar para seguimiento', 'follow_up', 'Realizar llamada de seguimiento al contacto'],
            ['Enviar cotización', 'email', 'Preparar y enviar cotización actualizada'],
            ['Reunión de presentación', 'meeting', 'Agendar reunión para presentar productos'],
            ['Revisar contrato', 'other', 'Revisar términos del contrato con el cliente'],
            ['Actualizar pipeline', 'other', 'Actualizar etapa del pipeline para este contacto'],
            ['Enviar documentación', 'email', 'Enviar documentación técnica solicitada'],
            ['Llamada de cierre', 'call', 'Intentar cierre de la negociación'],
            ['Seguimiento post-venta', 'follow_up', 'Contactar al cliente para feedback post-venta'],
            ['Capacitación producto', 'meeting', 'Coordinar capacitación sobre el producto'],
            ['Firma de contrato', 'meeting', 'Coordinar firma de contrato'],
        ];

        $userIds = collect($asesores)->pluck('id')->merge(collect($supervisores)->pluck('id'))->toArray();
        $priorities = ['low', 'medium', 'high'];
        $statuses = ['pending', 'in_progress', 'completed'];

        for ($i = 0; $i < 60; $i++) {
            $tpl = $taskTemplates[array_rand($taskTemplates)];
            $userId = $userIds[array_rand($userIds)];
            $contactId = $contactIds[array_rand($contactIds)];
            $status = $statuses[array_rand($statuses)];
            $daysOffset = $status === 'completed' ? rand(-30, -1) : rand(-5, 10);

            Task::create([
                'contact_id' => $contactId,
                'assigned_to' => $userId,
                'created_by' => $admin->id,
                'title' => $tpl[0],
                'description' => $tpl[2] . ' #' . $contactId,
                'type' => $tpl[1],
                'priority' => $priorities[array_rand($priorities)],
                'status' => $status,
                'due_date' => now()->addDays($daysOffset),
                'completed_at' => $status === 'completed' ? now()->subDays(rand(1, 30)) : null,
            ]);
        }

        for ($i = 0; $i < 36; $i++) {
            $userId = $userIds[array_rand($userIds)];
            $contactId = $contactIds[array_rand($contactIds)];

            Reminder::create([
                'user_id' => $userId,
                'contact_id' => $contactId,
                'title' => ['Llamar', 'Enviar email', 'Seguimiento', 'Recordatorio visita', 'Cumpleaños'][array_rand(['Llamar', 'Enviar email', 'Seguimiento', 'Recordatorio visita', 'Cumpleaños'])],
                'description' => 'Recordatorio automático para contacto',
                'remind_at' => now()->addHours(rand(1, 72)),
                'is_notified' => (bool) rand(0, 1),
            ]);
        }

        $visitStatuses = ['scheduled', 'completed', 'cancelled'];
        for ($i = 0; $i < 30; $i++) {
            $userId = $userIds[array_rand($userIds)];
            $contactId = $contactIds[array_rand($contactIds)];
            $status = $visitStatuses[array_rand($visitStatuses)];

            Visit::create([
                'contact_id' => $contactId,
                'user_id' => $userId,
                'title' => 'Visita a ' . Contact::find($contactId)->name,
                'summary' => 'Visita comercial programada',
                'status' => $status,
                'scheduled_at' => $status === 'completed'
                    ? now()->subDays(rand(1, 20))
                    : now()->addDays(rand(0, 14)),
                'completed_at' => $status === 'completed' ? now()->subDays(rand(1, 20)) : null,
            ]);
        }

        for ($i = 0; $i < 48; $i++) {
            $userId = $userIds[array_rand($userIds)];
            $contactId = $contactIds[array_rand($contactIds)];

            CallLog::create([
                'contact_id' => $contactId,
                'user_id' => $userId,
                'direction' => ['incoming', 'outgoing'][array_rand(['incoming', 'outgoing'])],
                'status' => ['completed', 'missed', 'busy', 'failed'][array_rand(['completed', 'missed', 'busy', 'failed'])],
                'duration' => rand(30, 1800),
                'notes' => 'Llamada de seguimiento',
                'called_at' => now()->subDays(rand(0, 30))->subHours(rand(1, 12)),
            ]);
        }

        $goalTypes = ['contacts', 'follow_ups', 'sales', 'calls', 'visits'];
        foreach ($userIds as $uid) {
            foreach ($goalTypes as $gt) {
                $target = match ($gt) {
                    'contacts' => rand(15, 30),
                    'follow_ups' => rand(10, 20),
                    'sales' => rand(3, 10),
                    'calls' => rand(20, 50),
                    'visits' => rand(5, 15),
                };
                Goal::create([
                    'user_id' => $uid,
                    'created_by' => $admin->id,
                    'type' => $gt,
                    'target' => $target,
                    'progress' => rand(0, $target),
                    'start_date' => now()->startOfWeek(),
                    'end_date' => now()->endOfWeek(),
                ]);
            }
        }

        foreach ($contactIds as $cid) {
            if (rand(0, 1)) continue;
            $contact = Contact::find($cid);

            Note::create([
                'user_id' => $contact->assigned_to,
                'contact_id' => $cid,
                'content' => [
                    'Cliente interesado en nuestros servicios. Solicita demo.',
                    'Llamar la próxima semana para seguimiento.',
                    'Negociación avanzada, casi cerrada.',
                    'Solicitó más información sobre planes corporativos.',
                    'Posible venta cruzada de productos adicionales.',
                    'Cliente referido por otro contacto.',
                    'Requiere aprobación de gerencia para continuar.',
                ][array_rand([
                    'Cliente interesado en nuestros servicios. Solicita demo.',
                    'Llamar la próxima semana para seguimiento.',
                    'Negociación avanzada, casi cerrada.',
                    'Solicitó más información sobre planes corporativos.',
                    'Posible venta cruzada de productos adicionales.',
                    'Cliente referido por otro contacto.',
                    'Requiere aprobación de gerencia para continuar.',
                ])],
                'is_private' => (bool) rand(0, 1),
            ]);
        }

        $products = [
            ['name' => 'Plan Básico CRM', 'price' => 9990, 'category' => 'SaaS', 'sku' => 'CRM-BAS-001'],
            ['name' => 'Plan Profesional CRM', 'price' => 24990, 'category' => 'SaaS', 'sku' => 'CRM-PRO-002'],
            ['name' => 'Plan Enterprise CRM', 'price' => 59990, 'category' => 'SaaS', 'sku' => 'CRM-ENT-003'],
            ['name' => 'Consultoría Implementación', 'price' => 150000, 'category' => 'Servicios', 'sku' => 'CONS-IMP-001'],
            ['name' => 'Capacitación Equipo (x persona)', 'price' => 25000, 'category' => 'Servicios', 'sku' => 'TRAIN-001'],
            ['name' => 'Soporte Premium (mensual)', 'price' => 15000, 'category' => 'SaaS', 'sku' => 'SUP-PRE-001'],
            ['name' => 'Módulo WhatsApp', 'price' => 5000, 'category' => 'Add-on', 'sku' => 'ADD-WSP-001'],
            ['name' => 'Módulo Email Marketing', 'price' => 8000, 'category' => 'Add-on', 'sku' => 'ADD-EML-001'],
            ['name' => 'API Integración', 'price' => 35000, 'category' => 'Servicios', 'sku' => 'API-INT-001'],
            ['name' => 'Auditoría de Procesos', 'price' => 80000, 'category' => 'Servicios', 'sku' => 'AUDIT-001'],
        ];

        foreach ($products as $p) {
            \App\Models\Product::create([
                'name' => $p['name'],
                'price' => $p['price'],
                'category' => $p['category'],
                'sku' => $p['sku'],
                'description' => 'Producto ' . $p['name'],
                'created_by' => $admin->id,
            ]);
        }

        foreach ($contactIds as $index => $cid) {
            if ($index % 5 !== 0) continue;
            $contact = Contact::find($cid);
            if (!$contact || !$contact->deal_value || $contact->deal_value <= 0) continue;
            $product = \App\Models\Product::inRandomOrder()->first();
            if ($product) {
                $qty = rand(1, 5);
                \App\Models\DealItem::create([
                    'contact_id' => $cid,
                    'product_id' => $product->id,
                    'quantity' => $qty,
                    'price' => $product->price,
                ]);
            }
        }
    }
}
