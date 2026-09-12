export interface Dependencia {
    idDependencia: number
    nombre: string
    rfc: string
    tipoLicencia: 'Prueba gratuita' | 'Mensual' | 'Anual'
    fechaExpiracionLicencia: string
    correoContacto: string
    correoReportes: string
    telefono: string
    datosFacturacion: string
    activo: boolean
    fechaRegistro: string
    nivelUsuarios: 'Básico' | 'Intermedio' | 'Ilimitado'
    limiteUsuarios: number
    logo?: string
}

export const mockDependencias: Dependencia[] = [
    {
        idDependencia: 1,
        nombre: 'Automotriz del Bajío S.A. de C.V.',
        rfc: 'ABA-100520-H52',
        tipoLicencia: 'Prueba gratuita',
        fechaExpiracionLicencia: '2028-05-20',
        correoContacto: 'contacto@autobajio.com',
        correoReportes: 'sistemas@autobajio.com',
        telefono: '442-123-4567',
        datosFacturacion: 'Parque Industrial Querétaro #500, Querétaro, QRO',
        activo: true,
        fechaRegistro: '2023-01-15',
        nivelUsuarios: 'Básico',
        limiteUsuarios: 4,
        logo: '/logo_empresa.png'
    },
    {
        idDependencia: 2,
        nombre: 'Procesadora de Alimentos del Sur',
        rfc: 'PAS-050812-J89',
        tipoLicencia: 'Anual',
        fechaExpiracionLicencia: '2026-12-31',
        correoContacto: 'info@proalsur.mx',
        correoReportes: 'calidad@proalsur.mx',
        telefono: '999-987-6543',
        datosFacturacion: 'Av. Itzaes #200, Mérida, YUC',
        activo: true,
        fechaRegistro: '2023-03-10',
        nivelUsuarios: 'Básico',
        limiteUsuarios: 10
    },
    {
        idDependencia: 3,
        nombre: 'Soluciones Logísticas Rápidas',
        rfc: 'SLR-150201-K33',
        tipoLicencia: 'Mensual',
        fechaExpiracionLicencia: '2026-06-30',
        correoContacto: 'atencion@solorap.com',
        correoReportes: 'tracking@solorap.com',
        telefono: '55-5555-1010',
        datosFacturacion: 'Calle 5 de Mayo #12, Estado de México, MEX',
        activo: true,
        fechaRegistro: '2024-01-05',
        nivelUsuarios: 'Intermedio',
        limiteUsuarios: 20
    }
];

export const getDependenciaName = (id: number): string => {
    const dep = mockDependencias.find(d => d.idDependencia === id)
    return dep ? dep.nombre : 'Dependencia Desconocida'
}

export interface Subdependencia {
    idSubdependencia: number
    idDependencia: number
    nombre: string
    activo: boolean
}

export const mockSubdependencias: Subdependencia[] = [
    { idSubdependencia: 1, idDependencia: 1, nombre: 'Área de Fundición', activo: true },
    { idSubdependencia: 2, idDependencia: 1, nombre: 'Línea de Ensamblaje A', activo: true },
    { idSubdependencia: 3, idDependencia: 1, nombre: 'Almacén General', activo: true },
    { idSubdependencia: 4, idDependencia: 2, nombre: 'Planta de Empaque', activo: true },
    { idSubdependencia: 5, idDependencia: 2, nombre: 'Cuartos Fríos', activo: true },
    { idSubdependencia: 6, idDependencia: 2, nombre: 'Laboratorio de Calidad', activo: true },
    { idSubdependencia: 7, idDependencia: 3, nombre: 'Nave Zona Norte', activo: true },
    { idSubdependencia: 8, idDependencia: 3, nombre: 'Nave Zona Sur', activo: true },
    { idSubdependencia: 9, idDependencia: 3, nombre: 'Flotilla Terrestre', activo: true },
];

export const getSubdependenciaName = (id: number | undefined): string => {
    if (!id) return '---'
    const subdep = mockSubdependencias.find(s => s.idSubdependencia === id)
    return subdep ? subdep.nombre : 'Área Desconocida'
}