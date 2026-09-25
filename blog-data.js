// Diario Sunqu: artículos del blog (lo leen blog.html y articulo.html).
// Bloques del cuerpo: { p }, { ul: [[negrita, texto]] }, { quote, cite }, { img, cap }.
window.SUNQU_CATS = { guia: 'Guía', sabor: 'Sabor', comunidad: 'Comunidad', historia: 'Historia' };
window.SUNQU_POSTS = [
  {
    slug: 'aclimatarte-en-los-andes', cat: 'guia', date: '2026-09-12', read: 6, img: '1541637593725-923467404d17',
    title: 'Cómo aclimatarte en los Andes sin perder el primer día',
    excerpt: 'Por qué entramos por el Valle Sagrado y qué comer, beber y evitar las primeras 48 horas.',
    author: 'Equipo Sunqu', role: 'Travel Advisors',
    lead: 'El soroche no avisa. Llega en la primera noche en Cusco, a 3.400 metros, y se lleva el día que tanto planeaste. Por eso lo tratamos como una variable de diseño y no como una advertencia en letra pequeña.',
    sections: [
      { h: 'Empezar más abajo', blocks: [
        { p: 'La decisión más importante del viaje se toma antes de aterrizar: por dónde entras a los Andes. Si llegas directo a Cusco, tu cuerpo pasa del nivel del mar a 3.400 metros en una hora de vuelo.' },
        { p: 'En nuestros journeys entramos por el Valle Sagrado, a unos 2.800 metros, y dejamos Cusco para el final. Son 600 metros menos que cambian por completo cómo se siente el primer día.' },
        { quote: 'Queremos que tu primer día en los Andes sea tuyo, no de tu dolor de cabeza.', cite: 'Principio 06 · Acompañamos antes, durante y después' },
      ] },
      { h: 'Las primeras 48 horas', blocks: [
        { p: 'El cuerpo se adapta solo si le das tiempo. Estas son las reglas que repetimos a cada viajero:' },
        { ul: [['Camina despacio.', 'El primer día, sin caminatas largas ni escaleras apuradas.'], ['Bebe agua todo el día.', 'La altura deshidrata más de lo que parece.'], ['Come ligero.', 'Sopas, carbohidratos y porciones pequeñas en la cena.'], ['Deja el alcohol para después.', 'Una copa en altura pesa como tres al nivel del mar.']] },
        { p: 'El mate de coca ayuda a muchos viajeros, pero no reemplaza el descanso. Si tienes alguna condición médica, consulta a tu médico antes de viajar.' },
      ] },
      { h: 'Subir por etapas', blocks: [
        { p: 'La curva de altitud de cada journey sube de a poco: primero el valle, después las mesetas de Moray y Maras, y al final Cusco. Cada día tiene su altura publicada, para que sepas qué esperar antes de preguntar.' },
        { img: '1590835192370-3941b97e93fd', cap: 'Valle Sagrado: la puerta de entrada más amable a los Andes.' },
        { p: 'Tu Travel Advisor te envía el plan de aclimatación con el itinerario, y durante el viaje lo ajustamos contigo si el cuerpo pide más tiempo.' },
      ] },
    ],
  },
  {
    slug: 'la-picanteria-arequipena', cat: 'sabor', date: '2026-08-28', read: 5, img: '1535399831218-d5bd36d1a6b3',
    title: 'La picantería arequipeña, explicada por una picantera',
    excerpt: 'Chupe, rocoto relleno y chicha de guiñapo: la cocina que se resiste a desaparecer.',
    author: 'Equipo Sunqu', role: 'Diseño de experiencias',
    lead: 'Una picantería no es un restaurante con otro nombre. Es una cocina con anfitriona de nombre propio, con un menú que cambia según el día de la semana y con recetas que pasan de madre a hija.',
    sections: [
      { h: 'Una cocina con calendario', blocks: [
        { p: 'En Arequipa, cada día tiene su plato. La picantería sigue ese calendario desde hace generaciones: el menú no se elige, se respeta.' },
        { ul: [['Chupe de camarones.', 'El caldo espeso que muchos arequipeños esperan toda la semana.'], ['Rocoto relleno.', 'Picante, horneado y acompañado de pastel de papa.'], ['Chicha de guiñapo.', 'Maíz morado germinado y fermentado, servido en vaso grande.']] },
      ] },
      { h: 'Quién está detrás del fogón', blocks: [
        { p: 'Las picanteras sostienen la tradición. Muchas cocinan todavía con leña y en batán, la piedra de moler que da textura a los ajíes.' },
        { quote: 'Aquí no vienes a probar: vienes a cocinar conmigo. Y la receta te la llevas.', cite: 'Picantera anfitriona · Arequipa' },
        { img: '1535400875775-0269e7a919af', cap: 'Ajíes, maíz y paciencia: la base de la cocina arequipeña.' },
      ] },
      { h: 'Por qué la elegimos', blocks: [
        { p: 'Arequipa no se eligió porque sea mejor que Cusco, sino porque la picantería es un formato culinario que un restaurante de autor no ofrece: una persona con nombre propio que te enseña y te recibe.' },
        { p: 'Por eso es el centro del journey El Perú que se Saborea, y la puerta de entrada del Módulo gastronómico.' },
      ] },
    ],
  },
  {
    slug: 'ayni-ficha-de-reciprocidad', cat: 'comunidad', date: '2026-08-09', read: 5, img: '1568805778796-96b66708d6e0',
    title: 'Ayni: qué recibe cada comunidad que te recibe',
    excerpt: 'Nuestra ficha de reciprocidad, paso a paso, y quién decide cómo se usa.',
    author: 'Equipo Sunqu', role: 'Relación con comunidades',
    lead: 'Ayni es la reciprocidad andina: hoy te ayudo, mañana me ayudas. Lo tomamos como regla de trabajo y lo publicamos en una ficha para que cualquier viajero pueda leerla.',
    sections: [
      { h: 'Qué es la ficha', blocks: [
        { p: 'Cada comunidad anfitriona tiene una ficha de reciprocidad. Dice qué recibe de tu viaje, cómo se decidió ese acuerdo y quién lo administra.' },
        { ul: [['Qué recibe.', 'El pago por la experiencia y el aporte al fondo comunal.'], ['Cómo se decidió.', 'En asamblea, con la comunidad y no solo con un intermediario.'], ['Quién lo administra.', 'Una persona o comité elegido por la propia comunidad.']] },
      ] },
      { h: 'Quién cuenta la historia', blocks: [
        { p: 'La comunidad tiene poder de veto sobre cómo se cuenta su historia: qué fotos se publican, qué palabras se usan y qué partes de su vida no son parte del viaje.' },
        { quote: 'No vendemos comunidades. Viajamos con ellas.', cite: 'Protección cultural · Sunqu Journeys' },
      ] },
      { h: 'Cómo leerla antes de reservar', blocks: [
        { img: '1730423284218-a8a7e9422ebf', cap: 'La ficha se revisa cada año con cada comunidad anfitriona.' },
        { p: 'Encontrarás la ficha en la página de cada journey, en la sección Conecta. Si una comunidad no tiene ficha publicada, esa experiencia todavía no se vende.' },
      ] },
    ],
  },
  {
    slug: 'que-es-un-momento-sunqu', cat: 'comunidad', date: '2026-07-24', read: 4, img: '1593460915132-fcb729cc4597',
    title: 'Qué es un Momento Sunqu (y qué no lo es)',
    excerpt: 'Cuatro condiciones para que una experiencia merezca nombre propio.',
    author: 'Equipo Sunqu', role: 'Diseño de experiencias',
    lead: 'Un viaje puede estar lleno de actividades y no tener ni un solo momento que recuerdes. Un Momento Sunqu es lo contrario: pocas cosas, pero con nombre propio.',
    sections: [
      { h: 'Las cuatro condiciones', blocks: [
        { p: 'Para que una experiencia sea un Momento Sunqu tiene que cumplir las cuatro, no solo alguna:' },
        { ul: [['Tiene nombre propio.', 'Detrás hay una persona que conoces por su nombre, no un proveedor.'], ['Tú haces algo.', 'Cocinas, tejes, siembras. No solo miras.'], ['Solo existe en el viaje.', 'No se puede reservar desde una app ni repetir en casa.'], ['Te llevas algo.', 'Una receta, un textil, una palabra, un vínculo.']] },
      ] },
      { h: 'Qué no es', blocks: [
        { p: 'Una foto en un mirador no es un Momento Sunqu. Tampoco una clase de cocina en un hotel ni una visita a un mercado donde nadie te habla.' },
        { quote: 'La pachamanca del día 6, cocinada bajo tierra con una familia quechua.', cite: 'El Momento Sunqu de Sunqu Origen' },
      ] },
      { h: 'Uno por viaje, como mínimo', blocks: [
        { p: 'Cada journey se diseña alrededor de al menos un Momento Sunqu. Lo demás, el transporte, los hoteles y los horarios, existe para que ese momento suceda bien.' },
      ] },
    ],
  },
  {
    slug: 'machu-picchu-al-principio', cat: 'historia', date: '2026-07-10', read: 5, img: '1532996152552-eaffc4edfc1a',
    title: 'Machu Picchu al principio: por qué cambiamos el orden del viaje',
    excerpt: 'Cuando el monumento es la premisa y no el clímax, lo que viene después se vuelve lo más importante.',
    author: 'Equipo Sunqu', role: 'Diseño de experiencias',
    lead: 'Casi todos los itinerarios guardan Machu Picchu para el final. Nosotros, en Perú Profundo, lo ponemos al inicio. No es una excentricidad: es la historia que queremos contar.',
    sections: [
      { h: 'El problema del clímax', blocks: [
        { p: 'Cuando Machu Picchu es el clímax, todo lo anterior se vuelve preparación y todo lo posterior, anticlímax. Los días en las comunidades se sienten como relleno.' },
      ] },
      { h: 'Primero la piedra, después la gente', blocks: [
        { p: 'Al ver la ciudadela primero, el viaje cambia de pregunta. Ya no es cómo llegar al monumento, sino quiénes son hoy los descendientes de quienes lo construyeron.' },
        { img: '1567597243073-2d274aabecec', cap: 'Machu Picchu, a 2.430 metros: la premisa del viaje.' },
        { quote: 'Lo que recordarás no es la piedra.', cite: 'Perú Profundo' },
      ] },
      { h: 'Qué viene después', blocks: [
        { ul: [['Inmersión comunitaria.', 'Días con una familia anfitriona, con su ritmo y sus tareas.'], ['Ficha de reciprocidad.', 'En este viaje es un requisito, no un extra.'], ['Tiempo sin agenda.', 'Espacio para que las conversaciones sucedan.']] },
      ] },
    ],
  },
  {
    slug: 'primeras-palabras-en-quechua', cat: 'guia', date: '2026-06-26', read: 4, img: '1568805778734-f0a5a77d7272',
    title: 'Tus primeras palabras en quechua antes de volar',
    excerpt: 'Sunqu, añay, ayni y otras palabras que abren conversaciones en los Andes.',
    author: 'Equipo Sunqu', role: 'Travel Advisors',
    lead: 'No necesitas hablar quechua para viajar por los Andes. Pero saludar en la lengua de quien te recibe cambia la primera conversación. Estas palabras vienen en tu kit de preparación.',
    sections: [
      { h: 'Para saludar', blocks: [
        { ul: [['Allillanchu.', '¿Estás bien? Es el saludo más común.'], ['Allillanmi.', 'Estoy bien. La respuesta.'], ['Sulpayki.', 'Gracias, en el quechua de Cusco.']] },
      ] },
      { h: 'Las palabras de la marca', blocks: [
        { ul: [['Sunqu.', 'Corazón. De ahí viene nuestro nombre.'], ['Ayni.', 'Reciprocidad: hoy por ti, mañana por mí.'], ['Añay.', 'Gracias, en varias regiones de los Andes.']] },
        { quote: 'Tupananchiskama: hasta que nos volvamos a encontrar.', cite: 'La despedida que más repiten nuestros anfitriones' },
      ] },
      { h: 'Cómo practicarlas', blocks: [
        { p: 'Tu Travel Advisor te envía audios con la pronunciación antes del viaje. Y el primer día, tus anfitriones te corrigen con mucha más paciencia de la que imaginas.' },
      ] },
    ],
  },
  {
    slug: 'del-mercado-a-la-montana', cat: 'sabor', date: '2026-06-12', read: 6, img: '1632913582499-381c7c798787',
    title: 'Del mercado a la montaña: seguir un ingrediente en reversa',
    excerpt: 'Cómo se diseñó Sunqu Origen: del plato terminado en Lima a la chacra en los Andes.',
    author: 'Equipo Sunqu', role: 'Diseño de experiencias',
    lead: 'Sunqu Origen empieza donde casi todos los viajes gastronómicos terminan: en el plato. Desde ahí, el itinerario camina hacia atrás hasta la tierra y la familia que lo siembra.',
    sections: [
      { h: 'El plato terminado', blocks: [
        { p: 'El día 1 es una cena en Lima: el punto de llegada de todo lo que verás después. Al día siguiente, el mercado de Surquillo con un cocinero anfitrión.' },
      ] },
      { h: 'La técnica y la altura', blocks: [
        { p: 'En el Valle Sagrado, los andenes de Moray muestran cómo se cultivaba a distintas alturas, y las salineras de Maras cómo se cosecha la sal de la montaña.' },
        { img: '1535400255456-984241443b29', cap: 'Lo que llega al plato empezó muchos kilómetros y metros más arriba.' },
      ] },
      { h: 'La tierra', blocks: [
        { p: 'El día 6 llega la pachamanca con una familia quechua: carnes y tubérculos cocinados bajo tierra con piedras calientes. Es el Momento Sunqu del viaje.' },
        { quote: 'El orden de los días no se puede cambiar sin destruir el viaje.', cite: 'Sunqu Origen · Journey insignia' },
      ] },
    ],
  },
  {
    slug: 'el-telar-de-chinchero', cat: 'historia', date: '2026-05-29', read: 5, img: '1566793772361-1d5d9cefbd12',
    title: 'El telar de cintura de Chinchero, hilo por hilo',
    excerpt: 'Teñido natural, iconografía y el tiempo que toma un textil hecho a mano.',
    author: 'Equipo Sunqu', role: 'Relación con comunidades',
    lead: 'Un textil de Chinchero no se compra: se entiende. Detrás de cada pieza hay semanas de trabajo, tintes que salen de plantas e insectos, y dibujos que cuentan de dónde viene quien lo tejió.',
    sections: [
      { h: 'Del vellón al hilo', blocks: [
        { p: 'La lana de oveja o alpaca se lava, se hila a mano con pushka y se tiñe. Todo antes de que empiece el tejido.' },
        { ul: [['Rojo.', 'De la cochinilla, un insecto que vive en los cactus.'], ['Amarillo.', 'De flores y hojas de plantas andinas.'], ['Azul.', 'Del añil, el color que más tiempo toma fijar.']] },
      ] },
      { h: 'El telar de cintura', blocks: [
        { p: 'Un extremo del telar se ata a un poste y el otro a la cintura de la tejedora, que controla la tensión con su propio cuerpo. Por eso cada textil es único.' },
        { img: '1568805778796-96b66708d6e0', cap: 'La tensión del hilo depende del cuerpo de quien teje.' },
      ] },
      { h: 'Lo que te llevas', blocks: [
        { p: 'En El Alma de los Andes pasas una mañana en el telar con la tejedora. Si compras un textil, la etiqueta lleva su nombre y el de su comunidad.' },
        { quote: 'Sé exactamente a quién se lo compré.', cite: 'Lo que más nos dicen al volver' },
      ] },
    ],
  },
  {
    slug: 'cuando-viajar-al-peru', cat: 'guia', date: '2026-05-15', read: 5, img: '1590835192370-3941b97e93fd',
    title: 'Cuándo viajar al Perú: temporadas sin mitos',
    excerpt: 'Temporada seca, lluvias y meses de transición, destino por destino.',
    author: 'Equipo Sunqu', role: 'Travel Advisors',
    lead: 'No hay un mal mes para viajar al Perú, pero sí meses mejores para cada viaje. La costa y los Andes tienen estaciones casi opuestas, y eso cambia qué journey te conviene.',
    sections: [
      { h: 'Los Andes', blocks: [
        { p: 'De mayo a septiembre es la temporada seca: cielos despejados, días templados y noches frías. De diciembre a marzo llueve, y en febrero el Camino Inca cierra por mantenimiento.' },
        { ul: [['Ideal.', 'De mayo a septiembre.'], ['Buena.', 'Abril, octubre y noviembre, con menos viajeros.'], ['Lluvias.', 'De diciembre a marzo.']] },
      ] },
      { h: 'Arequipa y la costa', blocks: [
        { p: 'Arequipa tiene sol casi todo el año. En Lima, de mayo a noviembre el cielo amanece gris y fresco; de diciembre a abril es verano.' },
        { img: '1541637593725-923467404d17', cap: 'Temporada seca en los Andes: el mejor momento para caminar.' },
      ] },
      { h: 'Cómo elegir', blocks: [
        { p: 'Cada página de journey tiene su calendario con los meses ideales. Si tus fechas no coinciden, tu Travel Advisor ajusta el orden del viaje para aprovechar el clima.' },
      ] },
    ],
  },
];
