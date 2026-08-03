import fs from "node:fs/promises";
import { execFileSync } from "node:child_process";
import { SpreadsheetFile, Workbook } from "@oai/artifact-tool";

const projectDir = "/Users/marcosborges/Documents/GitHub/ZentrumTask";
const outputDir = `${projectDir}/outputs/kanban-entrada-2026-07-27`;

const php = String.raw`
$stage = App\Models\Stage::query()
    ->where('is_initial', true)
    ->whereHas('board', fn ($query) => $query->where('is_active', true))
    ->with('board:id,name')
    ->orderBy('position')
    ->firstOrFail();

$tasks = App\Models\Task::query()
    ->where('board_id', $stage->board_id)
    ->where('stage_id', $stage->id)
    ->with([
        'assignedTo:id,name,email',
        'tags:id,name',
        'comments.user:id,name,email',
        'attachments',
    ])
    ->orderBy('position')
    ->orderBy('id')
    ->get();

echo json_encode([
    'board' => $stage->board->name,
    'stage' => $stage->name,
    'exported_at' => now()->toIso8601String(),
    'tasks' => $tasks->toArray(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
`;

const json = execFileSync("php", ["artisan", "tinker", "--execute", php], {
  cwd: projectDir,
  encoding: "utf8",
  maxBuffer: 20 * 1024 * 1024,
});
const data = JSON.parse(json);

const workbook = Workbook.create();
const tasksSheet = workbook.worksheets.add("Tasks - Entrada");
const commentsSheet = workbook.worksheets.add("Comentários");
const attachmentsSheet = workbook.worksheets.add("Anexos");

const parseDate = (value) => value ? new Date(value) : null;
const textValue = (value) => value === null || value === undefined ? "" : String(value);
const metaKeys = [...new Set(data.tasks.flatMap((task) => Object.keys(task.meta ?? {})))];

const taskHeaders = [
  "ID", "Board", "Estado", "Título", "Descrição", "Prioridade", "Responsável",
  "Email responsável", "Data limite", "Posição", "Primeira interação", "Entrada no estado",
  "Referência externa", "Tags", "N.º comentários", "N.º anexos", "Criado em", "Atualizado em",
  ...metaKeys.map((key) => `Meta: ${key}`), "Meta completo (JSON)",
];

const taskRows = data.tasks.map((task) => [
  task.id,
  data.board,
  data.stage,
  textValue(task.title),
  textValue(task.description),
  textValue(task.priority),
  textValue(task.assigned_to?.name),
  textValue(task.assigned_to?.email),
  parseDate(task.due_at),
  task.position ?? 0,
  parseDate(task.first_interaction_at),
  parseDate(task.stage_entered_at),
  textValue(task.external_reference),
  (task.tags ?? []).map((tag) => tag.name).join(", "),
  (task.comments ?? []).length,
  (task.attachments ?? []).length,
  parseDate(task.created_at),
  parseDate(task.updated_at),
  ...metaKeys.map((key) => {
    const value = task.meta?.[key];
    if (typeof value === "object" && value !== null) return JSON.stringify(value);
    if (typeof value === "boolean") return value;
    return textValue(value);
  }),
  task.meta ? JSON.stringify(task.meta, null, 2) : "",
]);

const taskLastColumn = columnName(taskHeaders.length);
tasksSheet.getRange(`A1:${taskLastColumn}1`).values = [[...taskHeaders]];
if (taskRows.length > 0) {
  tasksSheet.getRange(`A2:${taskLastColumn}${taskRows.length + 1}`).values = taskRows;
  const table = tasksSheet.tables.add(`A1:${taskLastColumn}${taskRows.length + 1}`, true, "TasksEntradaTable");
  table.style = "TableStyleMedium2";
  table.showFilterButton = true;
}

styleSheet(tasksSheet, taskHeaders.length, taskRows.length + 1);
tasksSheet.freezePanes.freezeRows(1);
tasksSheet.freezePanes.freezeColumns(3);
tasksSheet.getRange(`I2:I${taskRows.length + 1}`).format.numberFormat = "yyyy-mm-dd hh:mm";
tasksSheet.getRange(`K2:L${taskRows.length + 1}`).format.numberFormat = "yyyy-mm-dd hh:mm";
tasksSheet.getRange(`Q2:R${taskRows.length + 1}`).format.numberFormat = "yyyy-mm-dd hh:mm";
tasksSheet.getRange(`D2:E${taskRows.length + 1}`).format.wrapText = true;
tasksSheet.getRange(`${taskLastColumn}2:${taskLastColumn}${taskRows.length + 1}`).format.wrapText = true;
tasksSheet.getRange(`F2:F${taskRows.length + 1}`).conditionalFormats.add("containsText", {
  text: "critical",
  format: { fill: "#FECACA", font: { color: "#991B1B", bold: true } },
});
tasksSheet.getRange(`F2:F${taskRows.length + 1}`).conditionalFormats.add("containsText", {
  text: "high",
  format: { fill: "#FED7AA", font: { color: "#9A3412" } },
});

const commentHeaders = ["Task ID", "Título do task", "Comentário ID", "Autor", "Email do autor", "Interno", "Comentário", "Criado em", "Atualizado em"];
const commentRows = data.tasks.flatMap((task) => (task.comments ?? []).map((comment) => [
  task.id, task.title, comment.id, textValue(comment.user?.name), textValue(comment.user?.email),
  Boolean(comment.is_internal), textValue(comment.body), parseDate(comment.created_at), parseDate(comment.updated_at),
]));
writeRelatedSheet(commentsSheet, commentHeaders, commentRows, "CommentsEntradaTable");
commentsSheet.getRange(`H2:I${Math.max(2, commentRows.length + 1)}`).format.numberFormat = "yyyy-mm-dd hh:mm";

const attachmentHeaders = ["Task ID", "Título do task", "Anexo ID", "Nome original", "Tipo MIME", "Tamanho (bytes)", "Disco", "Caminho", "URL", "Criado em", "Atualizado em"];
const attachmentRows = data.tasks.flatMap((task) => (task.attachments ?? []).map((attachment) => [
  task.id, task.title, attachment.id, textValue(attachment.original_name), textValue(attachment.mime_type),
  attachment.size ?? 0, textValue(attachment.disk), textValue(attachment.path), textValue(attachment.url),
  parseDate(attachment.created_at), parseDate(attachment.updated_at),
]));
writeRelatedSheet(attachmentsSheet, attachmentHeaders, attachmentRows, "AttachmentsEntradaTable");
attachmentsSheet.getRange(`J2:K${Math.max(2, attachmentRows.length + 1)}`).format.numberFormat = "yyyy-mm-dd hh:mm";

await fs.mkdir(outputDir, { recursive: true });

for (const sheetName of ["Tasks - Entrada", "Comentários", "Anexos"]) {
  const preview = await workbook.render({ sheetName, autoCrop: "all", scale: 1, format: "png" });
  const safeName = sheetName.normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/[^a-zA-Z0-9]+/g, "-").toLowerCase();
  await fs.writeFile(`${outputDir}/preview-${safeName}.png`, new Uint8Array(await preview.arrayBuffer()));
}

console.log((await workbook.inspect({
  kind: "table",
  range: `Tasks - Entrada!A1:H${Math.min(taskRows.length + 1, 8)}`,
  include: "values,formulas",
  tableMaxRows: 8,
  tableMaxCols: 8,
})).ndjson);

console.log((await workbook.inspect({
  kind: "match",
  searchTerm: "#REF!|#DIV/0!|#VALUE!|#NAME\\?|#N/A",
  options: { useRegex: true, maxResults: 100 },
  summary: "final formula error scan",
})).ndjson);

const output = await SpreadsheetFile.exportXlsx(workbook);
const outputPath = `${outputDir}/tasks-entrada-kanban.xlsx`;
await output.save(outputPath);
console.log(JSON.stringify({ outputPath, tasks: taskRows.length, comments: commentRows.length, attachments: attachmentRows.length }));

function writeRelatedSheet(sheet, headers, rows, tableName) {
  const lastColumn = columnName(headers.length);
  sheet.getRange(`A1:${lastColumn}1`).values = [[...headers]];
  if (rows.length > 0) {
    sheet.getRange(`A2:${lastColumn}${rows.length + 1}`).values = rows;
    const table = sheet.tables.add(`A1:${lastColumn}${rows.length + 1}`, true, tableName);
    table.style = "TableStyleMedium2";
    table.showFilterButton = true;
  } else {
    sheet.getRange("A2").values = [["Sem registos para os tasks atualmente em Entrada."]];
  }
  styleSheet(sheet, headers.length, Math.max(2, rows.length + 1));
  sheet.freezePanes.freezeRows(1);
}

function styleSheet(sheet, columnCount, rowCount) {
  const lastColumn = columnName(columnCount);
  sheet.showGridLines = false;
  sheet.getRange(`A1:${lastColumn}1`).format = {
    fill: "#1F4E78",
    font: { bold: true, color: "#FFFFFF" },
    rowHeight: 28,
    verticalAlignment: "center",
    wrapText: true,
    borders: { preset: "outside", style: "thin", color: "#17365D" },
  };
  const used = sheet.getRange(`A1:${lastColumn}${rowCount}`);
  used.format.autofitColumns();
  used.format.autofitRows();
  for (let col = 0; col < columnCount; col += 1) {
    const range = sheet.getRangeByIndexes(0, col, rowCount, 1);
    const width = range.format.columnWidth;
    if (width > 32) range.format.columnWidth = 32;
    if (width < 10) range.format.columnWidth = 10;
  }
  if (rowCount > 1) {
    sheet.getRange(`A2:${lastColumn}${rowCount}`).format.verticalAlignment = "top";
  }
}

function columnName(number) {
  let result = "";
  let value = number;
  while (value > 0) {
    value -= 1;
    result = String.fromCharCode(65 + (value % 26)) + result;
    value = Math.floor(value / 26);
  }
  return result;
}
