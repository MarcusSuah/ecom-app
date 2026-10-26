import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';

export function UserAvatar({
    name,
    avatar,
}: {
    name: string;
    avatar: string | null;
}) {
    const initials = name
        .split(' ')
        .map((p) => p[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();

    return (
        <Avatar>
            <AvatarImage src={avatar ?? undefined} alt={name} />
            <AvatarFallback>{initials}</AvatarFallback>
        </Avatar>
    );
}
