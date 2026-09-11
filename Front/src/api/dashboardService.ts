export interface DashboardStats {
    Total_horas: number;
    Total_horas_tecnico: any[];
    Cantidad_reportes_generados: number;
    Cantidad_servicios_programados: number;
    Cantidad_tickets_levantados: number;
    Cantidad_sp_vencidos: number;
    Cantidad_sp_realizados_en_tiempo: number;
}

export const getDashboardStats = (): Promise<DashboardStats> => {
    return new Promise((resolve) => {
        setTimeout(() => {
            resolve({
                Total_horas: 142.5,
                Total_horas_tecnico: [],
                Cantidad_reportes_generados: 28,
                Cantidad_servicios_programados: 15,
                Cantidad_tickets_levantados: 12,
                Cantidad_sp_vencidos: 4,
                Cantidad_sp_realizados_en_tiempo: 11
            });
        }, 800);
    });
};