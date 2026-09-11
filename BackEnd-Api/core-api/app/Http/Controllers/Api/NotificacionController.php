<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notificacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\NotificacionMail;

class NotificacionController extends Controller
{
    public function index(Request $request)
    {
        $q = Notificacion::where('dependencia_id', $request->user()->dependencia_id);
        if ($request->estatus) $q->where('estatus', $request->estatus);
        if ($request->referencia_tipo) $q->where('referencia_tipo', $request->referencia_tipo);
        if ($request->has('leida')) $q->whereNull('leida_at');
        $q->orderByDesc('created_at');
        return $q->paginate($request->get('per_page', 15));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tipo'=>'required|string|max:50',
            'titulo'=>'nullable|string|max:200',
            'referencia_tipo'=>'required|in:ticket,servicio,reporte',
            'referencia_id'=>'required|integer',
            'destinatario'=>'nullable|email',
            'usuario_id'=>'nullable|exists:usuarios,id',
            'asunto'=>'required|string|max:200',
            'cuerpo'=>'nullable|string',
        ]);
        $data['dependencia_id'] = $request->user()->dependencia_id;
        $data['estatus'] = 'pendiente';
        $data['created_at'] = now();
        if (!isset($data['destinatario']) && isset($data['usuario_id'])) {
            $u = \App\Models\Usuario::find($data['usuario_id']);
            $data['destinatario'] = $u?->email;
        }
        $notif = Notificacion::create($data);

        // Envío inmediato por Gmail si hay destinatario
        if ($notif->destinatario) {
            try {
                Mail::to($notif->destinatario)->send(new NotificacionMail($notif));
                $notif->update(['estatus'=>'enviado','enviado_at'=>now()]);
            } catch (\Throwable $e) {
                $notif->update(['estatus'=>'fallido']);
            }
        }

        return response()->json($notif, 201);
    }

    public function markRead(Request $request, Notificacion $notificacion)
    {
        if ($notificacion->dependencia_id !== $request->user()->dependencia_id) abort(403);
        $notificacion->update(['leida_at'=>now()]);
        return response()->json($notificacion);
    }

    public function markAllRead(Request $request)
    {
        Notificacion::where('dependencia_id', $request->user()->dependencia_id)->whereNull('leida_at')->update(['leida_at'=>now()]);
        return response()->json(['message'=>'Todas marcadas como leídas']);
    }

    public function resend(Notificacion $notificacion)
    {
        if ($notificacion->destinatario) {
            Mail::to($notificacion->destinatario)->send(new NotificacionMail($notificacion));
            $notificacion->update(['estatus'=>'enviado','enviado_at'=>now()]);
        }
        return response()->json($notificacion);
    }
}
