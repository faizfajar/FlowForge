export const APP_TIME_ZONE = 'Asia/Jakarta';

const dateTimeFormatter = new Intl.DateTimeFormat('en-US', {
    timeZone: APP_TIME_ZONE,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false,
});

const datePart = (parts: Intl.DateTimeFormatPart[], type: Intl.DateTimeFormatPartTypes): string => {
    return parts.find((part) => part.type === type)?.value ?? '00';
};

export const formatDateTime = (value: string | null | undefined, fallback = '-'): string => {
    if (value === null || value === undefined || value === '') {
        return fallback;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return fallback;
    }

    const parts = dateTimeFormatter.formatToParts(date);

    return [
        datePart(parts, 'year'),
        datePart(parts, 'month'),
        datePart(parts, 'day'),
    ].join('-') + ` ${datePart(parts, 'hour')}:${datePart(parts, 'minute')}:${datePart(parts, 'second')}`;
};

export const formatDurationMs = (milliseconds: number): string => {
    const normalized = Math.max(0, Math.round(milliseconds));

    if (normalized < 1000) {
        return `${normalized}ms`;
    }

    return `${Math.round(normalized / 1000)}s`;
};

export const formatDurationBetween = (
    startedAt: string | null | undefined,
    completedAt: string | null | undefined,
    fallback = '-',
): string => {
    if (startedAt === null || startedAt === undefined) {
        return fallback;
    }

    if (completedAt === null || completedAt === undefined) {
        return 'running';
    }

    const started = new Date(startedAt).getTime();
    const completed = new Date(completedAt).getTime();

    if (Number.isNaN(started) || Number.isNaN(completed)) {
        return fallback;
    }

    return formatDurationMs(completed - started);
};
