export interface Usuario {
    idUsuario: number
    idDependencia: number
    numeroEmpleado: string
    rol: number
    nombre: string
    apellidos: string
    correo: string
    password?: string
    estado: number
    fechaCreacion: string
    ultimoAcceso: string
}


export const mockUsers: Usuario[] = [
    { idUsuario: 1, idDependencia: 1, numeroEmpleado: 'EMP-001', rol: 0, nombre: 'Carlos', apellidos: 'Mendoza', correo: 'cmendoza@empresa1.com', estado: 1, fechaCreacion: '2025-01-01', ultimoAcceso: '2026-02-01' },
    { idUsuario: 2, idDependencia: 1, numeroEmpleado: 'EMP-002', rol: 1, nombre: 'Eduardo', apellidos: 'Andrade', correo: 'eandrade@empresa1.com', estado: 1, fechaCreacion: '2025-01-01', ultimoAcceso: '2026-02-01' },
    { idUsuario: 3, idDependencia: 1, numeroEmpleado: 'EMP-003', rol: 2, nombre: 'Marcos', apellidos: 'Ruiz', correo: 'mruiz@empresa1.com', estado: 1, fechaCreacion: '2025-01-01', ultimoAcceso: '2026-02-01' },
    { idUsuario: 4, idDependencia: 1, numeroEmpleado: 'EMP-004', rol: 3, nombre: 'Pedro', apellidos: 'Sandoval', correo: 'psandoval@empresa1.com', estado: 1, fechaCreacion: '2025-01-01', ultimoAcceso: '2026-02-01' },
    { idUsuario: 5, idDependencia: 1, numeroEmpleado: 'EMP-005', rol: 3, nombre: 'Alejandro', apellidos: 'Garcia', correo: 'agarcia@empresa1.com', estado: 1, fechaCreacion: '2025-01-01', ultimoAcceso: '2026-02-01' },
    { idUsuario: 6, idDependencia: 1, numeroEmpleado: 'EMP-006', rol: 3, nombre: 'Beatriz', apellidos: 'Martinez', correo: 'bmartinez@empresa1.com', estado: 1, fechaCreacion: '2025-01-01', ultimoAcceso: '2026-02-01' },
    { idUsuario: 7, idDependencia: 1, numeroEmpleado: 'EMP-007', rol: 3, nombre: 'Daniela', apellidos: 'Lopez', correo: 'dlopez@empresa1.com', estado: 1, fechaCreacion: '2025-01-01', ultimoAcceso: '2026-02-01' },

    { idUsuario: 8, idDependencia: 2, numeroEmpleado: 'EMP-008', rol: 0, nombre: 'Miguel', apellidos: 'Castro', correo: 'mcastro@empresa2.com', estado: 1, fechaCreacion: '2025-01-01', ultimoAcceso: '2026-02-01' },
    { idUsuario: 9, idDependencia: 2, numeroEmpleado: 'EMP-009', rol: 1, nombre: 'Enrique', apellidos: 'Gonzalez', correo: 'egonzalez@empresa2.com', estado: 1, fechaCreacion: '2025-01-01', ultimoAcceso: '2026-02-01' },
    { idUsuario: 10, idDependencia: 2, numeroEmpleado: 'EMP-010', rol: 2, nombre: 'Fernanda', apellidos: 'Perez', correo: 'fperez@empresa2.com', estado: 1, fechaCreacion: '2025-01-01', ultimoAcceso: '2026-02-01' },
    { idUsuario: 11, idDependencia: 2, numeroEmpleado: 'EMP-011', rol: 3, nombre: 'Gabriel', apellidos: 'Sanchez', correo: 'gsanchez@empresa2.com', estado: 1, fechaCreacion: '2025-01-01', ultimoAcceso: '2026-02-01' },
    { idUsuario: 12, idDependencia: 2, numeroEmpleado: 'EMP-012', rol: 3, nombre: 'Laura', apellidos: 'Roman', correo: 'lroman@empresa2.com', estado: 1, fechaCreacion: '2025-01-01', ultimoAcceso: '2026-02-01' },
    { idUsuario: 13, idDependencia: 2, numeroEmpleado: 'EMP-013', rol: 3, nombre: 'Hilda', apellidos: 'Ramirez', correo: 'hramirez@empresa2.com', estado: 1, fechaCreacion: '2025-01-01', ultimoAcceso: '2026-02-01' },
    { idUsuario: 14, idDependencia: 2, numeroEmpleado: 'EMP-014', rol: 3, nombre: 'Sergio', apellidos: 'Blanco', correo: 'sblanco@empresa2.com', estado: 1, fechaCreacion: '2025-01-01', ultimoAcceso: '2026-02-01' },

    { idUsuario: 15, idDependencia: 3, numeroEmpleado: 'EMP-015', rol: 0, nombre: 'Luis', apellidos: 'Gomez', correo: 'lgomez@empresa3.com', estado: 1, fechaCreacion: '2025-01-01', ultimoAcceso: '2026-02-01' },
    { idUsuario: 16, idDependencia: 3, numeroEmpleado: 'EMP-016', rol: 1, nombre: 'Mariana', apellidos: 'Morales', correo: 'mmorales@empresa3.com', estado: 1, fechaCreacion: '2025-01-01', ultimoAcceso: '2026-02-01' },
    { idUsuario: 17, idDependencia: 3, numeroEmpleado: 'EMP-017', rol: 2, nombre: 'Nicolas', apellidos: 'Vazquez', correo: 'nvazquez@empresa3.com', estado: 1, fechaCreacion: '2025-01-01', ultimoAcceso: '2026-02-01' },
    { idUsuario: 18, idDependencia: 3, numeroEmpleado: 'EMP-018', rol: 3, nombre: 'Carmen', apellidos: 'Vega', correo: 'cvega@empresa3.com', estado: 1, fechaCreacion: '2025-01-01', ultimoAcceso: '2026-02-01' },
    { idUsuario: 19, idDependencia: 3, numeroEmpleado: 'EMP-019', rol: 3, nombre: 'Olivia', apellidos: 'Jimenez', correo: 'ojimenez@empresa3.com', estado: 1, fechaCreacion: '2025-01-01', ultimoAcceso: '2026-02-01' },
    { idUsuario: 20, idDependencia: 3, numeroEmpleado: 'EMP-020', rol: 3, nombre: 'Pablo', apellidos: 'Reyes', correo: 'preyes@empresa3.com', estado: 1, fechaCreacion: '2025-01-01', ultimoAcceso: '2026-02-01' },
    { idUsuario: 21, idDependencia: 3, numeroEmpleado: 'EMP-021', rol: 3, nombre: 'Roberto', apellidos: 'Diaz', correo: 'rdiaz@empresa3.com', estado: 1, fechaCreacion: '2025-01-01', ultimoAcceso: '2026-02-01' }
];

export const technicianList: string[] = mockUsers
    .filter(user => user.rol === 3)
    .map(user => `${user.nombre} ${user.apellidos}`);