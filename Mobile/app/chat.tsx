import { ChatScreen } from "@/src/screens/chat/chat-screen";
import { Stack } from "expo-router";

export default function Chat() {
  return (
    <>
      <Stack.Screen options={{ headerShown: false }} />
      <ChatScreen />
    </>
  );
}
