<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from "vue";
import Button from "primevue/button";
import Dialog from "primevue/dialog";
import InputText from "primevue/inputtext";
import Message from "primevue/message";
import Select from "primevue/select";
import Skeleton from "primevue/skeleton";
import Tag from "primevue/tag";
import { api, ApiError, type Slot, type SlotStatus } from "./api";

const slots = ref<Slot[]>([]);
const timezone = ref("UTC");
const selectedDate = ref("");
const loading = ref(false);
const busy = ref(false);
const loadError = ref("");
const actionError = ref("");
const notice = ref("");
const errors = ref<Record<string, string[]>>({});
const selected = ref<Slot | null>(null);
const mode = ref<"book" | "cancel">("book");
const dialog = ref(false);
const name = ref("");
const email = ref("");
const clock = ref(Date.now());
let clockOffset = 0;
let timer: ReturnType<typeof setInterval> | undefined;
const labels: Record<SlotStatus, string> = {
  available: "Свободно",
  booked: "Занято",
  past: "Прошло",
};
const severity: Record<SlotStatus, "success" | "warn" | "secondary"> = {
  available: "success",
  booked: "warn",
  past: "secondary",
};
const dateKey = (slot: Slot) => slot.starts_at.slice(0, 10);
const format = (value: string, options: Intl.DateTimeFormatOptions) =>
  new Intl.DateTimeFormat("ru-RU", {
    ...options,
    timeZone: timezone.value,
  }).format(new Date(value));
const time = (value: string) =>
  format(value, { hour: "2-digit", minute: "2-digit" });
const status = (slot: Slot): SlotStatus =>
  Date.parse(slot.starts_at) <= clock.value ? "past" : slot.status;
const dates = computed(() => [
  ...new Map(
    slots.value.map((slot) => [
      dateKey(slot),
      {
        value: dateKey(slot),
        label: format(slot.starts_at, {
          day: "numeric",
          month: "long",
          weekday: "short",
        }),
      },
    ]),
  ).values(),
]);
const visibleSlots = computed(() =>
  slots.value.filter((slot) => dateKey(slot) === selectedDate.value),
);
const availableCount = computed(
  () =>
    visibleSlots.value.filter((slot) => status(slot) === "available").length,
);
const openingHours = computed(() => {
  const firstDay = dates.value[0]?.value;
  const daySlots = slots.value.filter((slot) => dateKey(slot) === firstDay);
  const first = daySlots[0];
  const last = daySlots[daySlots.length - 1];
  return first && last ? `${time(first.starts_at)}–${time(last.ends_at)} · ежедневно` : '';
});

async function load() {
  if (loading.value) return;
  loading.value = true;
  loadError.value = "";
  try {
    const schedule = await api.slots();
    slots.value = schedule.data;
    timezone.value = schedule.timezone;
    clockOffset = Date.parse(schedule.server_time) - Date.now();
    clock.value = Date.now() + clockOffset;
    if (!dates.value.some((date) => date.value === selectedDate.value)) {
      selectedDate.value =
        schedule.data
          .find((slot) => status(slot) !== "past")
          ?.starts_at.slice(0, 10) ||
        dates.value[0]?.value ||
        "";
    }
  } catch (error) {
    loadError.value =
      error instanceof Error
        ? error.message
        : "Не удалось загрузить расписание.";
  } finally {
    loading.value = false;
  }
}

function open(slot: Slot, action: "book" | "cancel") {
  selected.value = slot;
  mode.value = action;
  name.value = "";
  email.value = "";
  errors.value = {};
  actionError.value = "";
  notice.value = "";
  dialog.value = true;
}

async function submit() {
  if (!selected.value || busy.value || loading.value) return;
  busy.value = true;
  errors.value = {};
  actionError.value = "";
  try {
    if (mode.value === "book") {
      const result = await api.book({
        slot_id: selected.value.id,
        customer_name: name.value.trim(),
        customer_email: email.value.trim(),
      });
      notice.value = `Бронирование №${result.id} создано.`;
    } else {
      if (selected.value.booking_id === null) return;
      await api.cancel(selected.value.booking_id);
      notice.value = "Бронирование отменено.";
    }
    dialog.value = false;
    await load();
  } catch (error) {
    if (error instanceof ApiError) {
      errors.value = error.errors;
      actionError.value =
        error.status === 422 ? "Проверьте поля формы." : error.message;
      if (error.status === 409 || error.status === 404) await load();
    } else {
      actionError.value = "Не удалось выполнить действие. Повторите попытку.";
    }
  } finally {
    busy.value = false;
  }
}

onMounted(() => {
  void load();
  timer = setInterval(() => {
    clock.value = Date.now() + clockOffset;
  }, 1000);
});
onUnmounted(() => {
  clearInterval(timer);
});
</script>

<template>
  <div class="page-shell">
    <header class="brand-bar">
      <a href="/" aria-label="Mini Booking — главная"
        ><span class="brand-mark" aria-hidden="true">m.</span>mini booking</a
      >
    </header>
    <main>
      <div class="heading-row">
        <h1>Переговорная</h1>
        <Tag v-if="openingHours" :value="openingHours" severity="secondary" />
      </div>
      <section
        class="schedule"
        aria-label="Расписание переговорной"
        :aria-busy="loading"
      >
        <div class="toolbar">
          <div class="date-filter">
            <label for="date">Дата</label
            ><Select
              input-id="date"
              v-model="selectedDate"
              :options="dates"
              option-label="label"
              option-value="value"
              :disabled="loading || busy || !dates.length"
              placeholder="Выберите дату"
            />
          </div>
          <div class="toolbar-end">
            <span class="timezone">{{ timezone }}</span
            ><Button
              label="Обновить"
              severity="secondary"
              outlined
              :loading="loading"
              :disabled="busy"
              @click="load"
            />
          </div>
        </div>
        <Message
          v-if="notice"
          severity="success"
          :closable="true"
          @close="notice = ''"
          >{{ notice }}</Message
        >
        <Message v-if="loadError" severity="error" :closable="false"
          ><div class="error-content">
            <span>{{ loadError }}</span
            ><Button
              label="Повторить загрузку"
              severity="danger"
              text
              :disabled="loading || busy"
              @click="load"
            /></div
        ></Message>
        <div class="list-caption">
          <span>Время и доступность</span
          ><span v-if="slots.length"
            >Свободно: {{ availableCount }} из {{ visibleSlots.length }}</span
          >
        </div>
        <div
          v-if="loading && !slots.length"
          class="slots-list"
          aria-label="Загрузка расписания"
        >
          <Skeleton v-for="i in 5" :key="i" height="5rem" />
        </div>
        <div v-else-if="!visibleSlots.length && !loadError" class="empty-state">
          На выбранную дату слотов нет.
        </div>
        <div v-else class="slots-list">
          <article
            v-for="slot in visibleSlots"
            :key="slot.id"
            class="slot"
            :class="`slot-${status(slot)}`"
          >
            <div class="slot-time">
              <span class="time-range"
                >{{ time(slot.starts_at) }}<span class="time-divider">—</span
                >{{ time(slot.ends_at) }}</span
              ><span class="slot-date">{{
                format(slot.starts_at, { day: "numeric", month: "long" })
              }}</span>
            </div>
            <Tag
              :value="labels[status(slot)]"
              :severity="severity[status(slot)]"
              class="status-tag"
            />
            <div class="slot-action">
              <Button
                v-if="status(slot) === 'available'"
                label="Забронировать"
                :disabled="busy || loading || !!loadError"
                @click="open(slot, 'book')"
              /><Button
                v-else-if="slot.booking_id !== null"
                label="Отменить"
                severity="secondary"
                outlined
                :disabled="busy || loading || !!loadError"
                @click="open(slot, 'cancel')"
              /><span
                v-else
                class="unavailable"
                aria-label="Бронирование недоступно"
                >—</span
              >
            </div>
          </article>
        </div>
      </section>
      <footer>
        <span class="legend-dot" aria-hidden="true"></span>Время указано в
        часовом поясе расписания. Один слот — один час.
      </footer>
    </main>
    <Dialog
      v-model:visible="dialog"
      modal
      :header="
        mode === 'book' ? 'Забронировать время' : 'Отменить бронирование?'
      "
      :closable="!busy"
      :close-on-escape="!busy"
      :draggable="false"
      class="booking-dialog"
    >
      <template v-if="selected">
        <div class="selection-summary">
          {{ format(selected.starts_at, { day: "numeric", month: "long" }) }} ·
          {{ time(selected.starts_at) }}–{{ time(selected.ends_at)
          }}<span>{{ timezone }}</span>
        </div>
        <Message v-if="actionError" severity="error" :closable="false">{{
          actionError
        }}</Message>
        <form
          v-if="mode === 'book'"
          class="booking-form"
          @submit.prevent="submit"
        >
          <div class="field">
            <label for="customer-name">Имя</label
            ><InputText
              id="customer-name"
              v-model="name"
              required
              maxlength="150"
              autocomplete="name"
              :disabled="busy"
              :invalid="!!errors.customer_name"
              :aria-describedby="
                errors.customer_name ? 'name-error' : undefined
              "
            /><small
              v-if="errors.customer_name"
              id="name-error"
              class="field-error"
              >{{ errors.customer_name.join(" ") }}</small
            >
          </div>
          <div class="field">
            <label for="customer-email">Email</label
            ><InputText
              id="customer-email"
              v-model="email"
              type="email"
              required
              maxlength="254"
              autocomplete="email"
              :disabled="busy"
              :invalid="!!errors.customer_email"
              :aria-describedby="
                errors.customer_email ? 'email-error' : undefined
              "
            /><small
              v-if="errors.customer_email"
              id="email-error"
              class="field-error"
              >{{ errors.customer_email.join(" ") }}</small
            >
          </div>
          <small v-if="errors.slot_id" class="field-error">{{
            errors.slot_id.join(" ")
          }}</small>
          <div class="dialog-actions">
            <Button
              label="Закрыть"
              severity="secondary"
              text
              :disabled="busy"
              @click="dialog = false"
            /><Button
              type="submit"
              :label="actionError ? 'Повторить бронирование' : 'Подтвердить'"
              :loading="busy"
              :disabled="loading"
            />
          </div>
        </form>
        <template v-else
          ><p class="cancel-copy">
            Будущий слот снова станет доступен для бронирования. В этом демо
            отмена доступна всем, кто знает ID бронирования.
          </p>
          <div class="dialog-actions">
            <Button
              label="Оставить"
              severity="secondary"
              text
              :disabled="busy"
              @click="dialog = false"
            /><Button
              label="Да, отменить"
              severity="danger"
              :loading="busy"
              :disabled="loading"
              @click="submit"
            /></div
        ></template>
      </template>
    </Dialog>
  </div>
</template>
