import MaterialIcons from "@expo/vector-icons/MaterialIcons";
import { useRouter } from "expo-router";
import { useState } from "react";
import {
    FlatList,
    KeyboardAvoidingView,
    Platform,
    Pressable,
    StyleSheet,
    Text,
    TextInput,
    View,
} from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";

type Message = {
  id: string;
  text: string;
  isBot: boolean;
  time: string;
};

const initialMessages: Message[] = [
  {
    id: "1",
    text: "Xin chào! Tôi là Trợ Lý AI của VigilantBus. Tôi có thể giúp gì cho chuyến đi của bạn?",
    isBot: true,
    time: "10:00",
  },
];

export function ChatScreen() {
  const router = useRouter();
  const [messages, setMessages] = useState<Message[]>(initialMessages);
  const [inputText, setInputText] = useState("");

  const sendMessage = () => {
    if (!inputText.trim()) return;

    const userMessage: Message = {
      id: Date.now().toString(),
      text: inputText.trim(),
      isBot: false,
      time: new Date().toLocaleTimeString([], {
        hour: "2-digit",
        minute: "2-digit",
      }),
    };

    setMessages((prev) => [...prev, userMessage]);
    setInputText("");

    // Simulate bot response
    setTimeout(() => {
      const botMessage: Message = {
        id: (Date.now() + 1).toString(),
        text: "Hệ thống đang tìm chuyến đi phù hợp cho bạn...",
        isBot: true,
        time: new Date().toLocaleTimeString([], {
          hour: "2-digit",
          minute: "2-digit",
        }),
      };
      setMessages((prev) => [...prev, botMessage]);
    }, 1000);
  };

  const renderMessage = ({ item }: { item: Message }) => {
    const isBot = item.isBot;
    return (
      <View
        style={[
          styles.messageRow,
          isBot ? styles.messageRowBot : styles.messageRowUser,
        ]}
      >
        {isBot && (
          <View style={styles.avatarBot}>
            <MaterialIcons name="smart-toy" size={20} color="#ffffff" />
          </View>
        )}
        <View
          style={[
            styles.messageBubble,
            isBot ? styles.messageBubbleBot : styles.messageBubbleUser,
          ]}
        >
          <Text
            style={[
              styles.messageText,
              isBot ? styles.messageTextBot : styles.messageTextUser,
            ]}
          >
            {item.text}
          </Text>
          <Text
            style={[
              styles.timeText,
              isBot ? styles.timeTextBot : styles.timeTextUser,
            ]}
          >
            {item.time}
          </Text>
        </View>
      </View>
    );
  };

  return (
    <SafeAreaView style={styles.safeArea} edges={["top", "bottom"]}>
      <KeyboardAvoidingView
        style={styles.container}
        behavior={Platform.OS === "ios" ? "padding" : undefined}
      >
        {/* Header */}
        <View style={styles.header}>
          <Pressable onPress={() => router.back()} style={styles.backButton}>
            <MaterialIcons name="arrow-back" size={24} color="#0f172a" />
          </Pressable>
          <View style={styles.headerTitleContainer}>
            <Text style={styles.headerTitle}>Trợ Lý AI</Text>
            <View style={styles.statusRow}>
              <View style={styles.dotOnline} />
              <Text style={styles.statusText}>Trực tuyến</Text>
            </View>
          </View>
          <View style={styles.placeholder} />
        </View>

        {/* Message List */}
        <FlatList
          data={messages}
          keyExtractor={(item) => item.id}
          renderItem={renderMessage}
          contentContainerStyle={styles.listContent}
          showsVerticalScrollIndicator={false}
        />

        {/* Input Area */}
        <View style={styles.inputContainer}>
          <View style={styles.inputWrapper}>
            <TextInput
              style={styles.input}
              placeholder="Nhập tin nhắn..."
              placeholderTextColor="#64748b"
              value={inputText}
              onChangeText={setInputText}
              multiline
            />
            <Pressable
              style={[
                styles.sendButton,
                !inputText.trim() && styles.sendButtonDisabled,
              ]}
              onPress={sendMessage}
              disabled={!inputText.trim()}
            >
              <MaterialIcons
                name="send"
                size={20}
                color={inputText.trim() ? "#ffffff" : "#94a3b8"}
              />
            </Pressable>
          </View>
        </View>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: "#ffffff",
  },
  container: {
    flex: 1,
    backgroundColor: "#f8fafc",
  },
  header: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    paddingHorizontal: 16,
    paddingVertical: 12,
    backgroundColor: "#ffffff",
    borderBottomWidth: 1,
    borderBottomColor: "#e2e8f0",
  },
  backButton: {
    padding: 8,
  },
  headerTitleContainer: {
    alignItems: "center",
  },
  headerTitle: {
    fontSize: 18,
    fontWeight: "700",
    color: "#0f172a",
  },
  statusRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 4,
    marginTop: 2,
  },
  dotOnline: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: "#22c55e",
  },
  statusText: {
    fontSize: 12,
    color: "#64748b",
  },
  placeholder: {
    width: 40,
  },
  listContent: {
    padding: 16,
    gap: 16,
  },
  messageRow: {
    flexDirection: "row",
    alignItems: "flex-end",
    maxWidth: "80%",
    gap: 8,
  },
  messageRowBot: {
    alignSelf: "flex-start",
  },
  messageRowUser: {
    alignSelf: "flex-end",
  },
  avatarBot: {
    width: 32,
    height: 32,
    borderRadius: 16,
    backgroundColor: "#0052cc",
    justifyContent: "center",
    alignItems: "center",
  },
  messageBubble: {
    paddingInline: 14,
    paddingBlock: 10,
    borderRadius: 18,
  },
  messageBubbleBot: {
    backgroundColor: "#ffffff",
    borderBottomLeftRadius: 4,
    borderWidth: 1,
    borderColor: "#e2e8f0",
  },
  messageBubbleUser: {
    backgroundColor: "#0052cc",
    borderBottomRightRadius: 4,
  },
  messageText: {
    fontSize: 15,
    lineHeight: 22,
  },
  messageTextBot: {
    color: "#0f172a",
  },
  messageTextUser: {
    color: "#ffffff",
  },
  timeText: {
    fontSize: 11,
    marginTop: 4,
    alignSelf: "flex-end",
  },
  timeTextBot: {
    color: "#94a3b8",
  },
  timeTextUser: {
    color: "#bfdbfe",
  },
  inputContainer: {
    paddingHorizontal: 16,
    paddingVertical: 12,
    backgroundColor: "#ffffff",
    borderTopWidth: 1,
    borderTopColor: "#e2e8f0",
  },
  inputWrapper: {
    flexDirection: "row",
    alignItems: "flex-end",
    backgroundColor: "#f1f5f9",
    borderRadius: 24,
    paddingHorizontal: 16,
    paddingVertical: 8,
    gap: 12,
  },
  input: {
    flex: 1,
    minHeight: 32,
    maxHeight: 120,
    fontSize: 15,
    color: "#0f172a",
    paddingTop: Platform.OS === "ios" ? 6 : 0, // Align text vertically
  },
  sendButton: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: "#0052cc",
    justifyContent: "center",
    alignItems: "center",
  },
  sendButtonDisabled: {
    backgroundColor: "#e2e8f0",
  },
});
