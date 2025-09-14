export function topicLink(topicId: number) {
    const username = process.env.TELEGRAM_GROUP_USERNAME;
    const chatId = process.env.TELEGRAM_INTERNAL_CHAT_ID; // "-100123..."

    if (username && username.trim()) {
        return `https://t.me/${username}/${topicId}`;
    }

    if (chatId && chatId.trim()) {
        const absId = String(Math.abs(Number(chatId)));
        return `https://t.me/c/${absId}/${topicId}`;
    }

    throw new Error('Set TELEGRAM_GROUP_USERNAME or TELEGRAM_INTERNAL_CHAT_ID');
}
