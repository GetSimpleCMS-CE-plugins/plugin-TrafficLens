<?php if (!defined('IN_GS')) { die('You cannot load this page directly.'); }

$i18n = [

	// ── Barra lateral ────────────────────────────────────────────────────────
	'lang_Menu_Title'		=> 'TrafficLens',

	// ── Barra superior ───────────────────────────────────────────────────────
	'lang_Range_Today'		=> 'Hoy',
	'lang_Range_7d'			=> 'Últimos 7 días',
	'lang_Range_30d'		=> 'Últimos 30 días',
	'lang_Range_90d'		=> 'Últimos 90 días',
	'lang_Range_365d'		=> 'Último año',
	'lang_Range_All'		=> 'Todo el tiempo',
	'lang_Refresh'			=> 'Actualizar',
	'lang_Export_CSV'		=> 'Exportar CSV',
	'lang_Export_PDF'		=> 'Exportar PDF',
	'lang_Settings'			=> 'Ajustes',

	// ── Panel de ajustes ─────────────────────────────────────────────────────
	'lang_Exclude_Admin'	=> 'Excluir mis propias visitas mientras esté conectado',
	'lang_Bots_Label'		=> 'Tráfico de bots',
	'lang_Bots_Include'		=> 'Contar los bots como visitantes',
	'lang_Bots_Separate'	=> 'Excluir los bots de las estadísticas (se siguen registrando)',
	'lang_Bots_Skip'		=> 'No registrar bots',
	'lang_Bots_Help'		=> 'Los bots ya registrados permanecen en la base de datos, así que puedes volver atrás en cualquier momento. "No registrar bots" solo se aplica a las visitas nuevas.',
	'lang_Keep_Data_For'	=> 'Conservar datos durante',
	'lang_Days'				=> 'días',
	'lang_Auto_Refresh_Every' => 'Actualizar la actividad en vivo cada',
	'lang_Seconds'			=> 'segundos',
	'lang_Save_Settings'	=> 'Guardar ajustes',
	'lang_Saving'			=> 'Guardando...',
	'lang_Saved'			=> 'Guardado.',
	'lang_Save_Failed'		=> 'Error al guardar.',

	// ── Tarjetas de estadísticas ─────────────────────────────────────────────
	'lang_Total_Pageviews'	=> 'Visitas totales de página',
	'lang_Unique_Visitors'	=> 'Visitantes únicos',
	'lang_Today'			=> 'Hoy',
	'lang_Live_Now'			=> 'En vivo ahora',

	// ── Títulos de gráficos / secciones ──────────────────────────────────────
	'lang_Daily_Visitors'	=> 'Visitantes diarios',
	'lang_Visitors_By_Hour'	=> 'Visitantes por hora (hoy)',
	'lang_Devices'			=> 'Dispositivos',
	'lang_Browsers'			=> 'Navegadores',
	'lang_Operating_Systems' => 'Sistemas operativos',
	'lang_Peak_Hours'		=> 'Horas de mayor actividad',
	'lang_Day_Of_Week'		=> 'Día de la semana',
	'lang_New_Vs_Returning'	=> 'Nuevos y recurrentes',
	'lang_Top_Countries'	=> 'Países principales',
	'lang_Top_Referrers'	=> 'Referentes principales',
	'lang_Entry_Pages'		=> 'Páginas de entrada',
	'lang_Exit_Pages'		=> 'Páginas de salida',
	'lang_Top_Pages'		=> 'Páginas principales',
	'lang_Live_Activity'	=> 'Actividad en vivo',
	'lang_Bot_Activity'		=> 'Actividad de bots',

	// ── Etiquetas de series del gráfico ───────────────────────────────────────
	'lang_Series_Page_Views' => 'Visitas de página',

	// ── Tablas / listas ───────────────────────────────────────────────────────
	'lang_Search_URL'		=> 'Buscar URL...',
	'lang_Col_URL'			=> 'URL',
	'lang_Col_Views'		=> 'Visitas',
	'lang_Col_Uniques'		=> 'Únicos',
	'lang_Loading'			=> 'Cargando...',
	'lang_Generating'		=> 'Generando...',
	'lang_No_Data_Range'	=> 'No hay datos para este período.',
	'lang_No_Pages_Found'	=> 'No se encontraron páginas.',
	'lang_No_Recent_Activity' => 'No hay actividad reciente.',
	'lang_No_Bots'			=> 'No se registró tráfico de bots en este período.',
	'lang_Bot_Skip_Note'	=> 'Ya no se registran bots. Aquí se muestra lo registrado anteriormente.',
	'lang_Bot_Unidentified'	=> 'Bot no identificado',
	'lang_Bot_Unknown_Client' => 'Cliente desconocido (sin user agent)',
	'lang_Bot_Other'		=> 'Otro bot',
	'lang_Prev'				=> '« Anterior',
	'lang_Next'				=> 'Siguiente »',

	// Las plantillas usan marcadores {placeholder} - consérvalos al traducir,
	// pero puedes reordenarlos según sea necesario.
	'lang_Pagination_Template'	=> 'Página {page} de {totalPages} ({total} páginas registradas)',
	'lang_Online_Now_Template'	=> '({count} en línea ahora)',
	'lang_Bounce_Rate_Template'	=> '({rate}% de rebote)',
	'lang_Bot_Summary_Template'	=> '({hits} visitas, {pct}% del tráfico total)',
	'lang_Bot_Last_Seen_Template' => 'visto por última vez {time}',
	'lang_Newret_New_Template'	=> 'Nuevos: {count} ({pct}%)',
	'lang_Newret_Returning_Template' => 'Recurrentes: {count} ({pct}%)',
	'lang_Newret_Unavailable'	=> 'Selecciona un período más corto (no "Todo el tiempo") para ver los visitantes nuevos y recurrentes.',

	// ── Pie de página ─────────────────────────────────────────────────────────
	'lang_Footer_Country_Data'	=> 'Datos de país',
	'lang_Footer_Licensed_Under' => 'con licencia',

	// ── Exportación a PDF ────────────────────────────────────────────────────
	'lang_Pdf_Title'		=> 'Informe de TrafficLens',
	'lang_Pdf_Generated'	=> 'Generado',
	'lang_Pdf_Stat_Line_Template'	=> 'Visitas totales: {total}     Visitantes únicos: {unique}     Hoy: {today}     En vivo ahora: {live}',
	'lang_Pdf_Newret_Title'	=> 'Visitantes Nuevos y Recurrentes',
	'lang_Pdf_Newret_Unavailable'	=> 'No disponible para el período "Todo el tiempo".',
	'lang_Pdf_Col_Country'	=> 'País',
	'lang_Pdf_Col_Referrer'	=> 'Referente',
	'lang_Pdf_Col_Visits'	=> 'Visitas',
	'lang_Pdf_Col_Entry_Page'	=> 'Página de Entrada',
	'lang_Pdf_Col_Exit_Page'	=> 'Página de Salida',
	'lang_Pdf_Col_Sessions'	=> 'Sesiones',
	'lang_Pdf_Bounce_Note_Template' => ' ({rate}% de rebote, {sessions} sesiones)',
	'lang_Pdf_Footer'	=> 'Datos de país (c) DB-IP, con licencia CC BY 4.0 - db-ip.com',
	'lang_Pdf_Export_Failed'	=> 'Error al exportar el PDF - revisa la consola del navegador para más detalles.',

];
