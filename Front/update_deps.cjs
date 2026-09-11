const fs = require('fs');
const content = fs.readFileSync('src/data/mockDependencias.ts', 'utf8');

let newContent = content.replace(
    /fechaRegistro:\s*string\s*\n\s*\n\s*\}/,
    "fechaRegistro: string\n    nivelUsuarios: 'Básico' | 'Intermedio' | 'Ilimitado'\n    limiteUsuarios: number\n}"
);

let count = 0;
newContent = newContent.replace(/(fechaRegistro:\s*'[^']+')(\n\s*\})/g, (match, p1, p2) => {
    count++;
    let nivel = 'Básico';
    let limite = 4;
    if (count === 1) {
        nivel = 'Básico';
        limite = 4;
    } else if (count % 3 === 0) {
        nivel = 'Intermedio';
        limite = 20;
    } else if (count % 5 === 0) {
        nivel = 'Ilimitado';
        limite = 100;
    } else {
        nivel = 'Básico';
        limite = 10;
    }
    return `${p1},\n        nivelUsuarios: '${nivel}',\n        limiteUsuarios: ${limite}${p2}`;
});

fs.writeFileSync('src/data/mockDependencias.ts', newContent);
console.log('Update complete. Items modified:', count);
